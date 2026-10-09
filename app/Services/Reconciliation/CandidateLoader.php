<?php

namespace App\Services\Reconciliation;

use App\Enums\ImportFileStatus;
use App\Enums\SessionStatus;
use App\Enums\SkipReason;
use App\Models\AuthorizationEntry;
use App\Models\PaymentEntry;
use App\Models\ReconciliationPairBlock;
use App\Models\ReconciliationSession;
use App\Services\ExcludedCodes\ExcludedCodeSnapshot;
use App\Services\ExcludedCodes\OperationCode;
use App\Services\Reconciliation\Matching\AuthorizationCandidate;
use App\Services\Reconciliation\Matching\EngineParameters;
use App\Services\Reconciliation\Matching\Matcher;
use App\Services\Reconciliation\Matching\PaymentCandidate;
use App\Services\Reconciliation\Matching\SupplierNameNormalizer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class CandidateLoader
{
    public function __construct(protected SupplierNameNormalizer $normalizer) {}

    /**
     * Read what a run compares: the payments of the session, its authorizations and the
     * authorizations still open in earlier processed sessions.
     */
    public function load(ReconciliationSession $session, EngineParameters $parameters, ExcludedCodeSnapshot $excludedCodes): LoadedCandidates
    {
        $skips = [];

        return new LoadedCandidates(
            authorizations: [
                ...$this->sessionAuthorizations($session, $parameters, $skips),
                ...$this->priorAuthorizations($session, $parameters),
            ],
            payments: $this->payments($session, $excludedCodes, $skips),
            skips: $skips,
            blockedPairs: $this->blockedPairs(),
        );
    }

    /**
     * @param  list<array<string, mixed>>  $skips
     * @return list<PaymentCandidate>
     */
    protected function payments(ReconciliationSession $session, ExcludedCodeSnapshot $excludedCodes, array &$skips): array
    {
        $duplicates = PaymentEntry::query()
            ->from('payment_entries as mine')
            ->join('payment_entries as other', fn ($join) => $join
                ->on('other.unit', '=', 'mine.unit')
                ->on('other.identity_key', '=', 'mine.identity_key'))
            ->join('reconciliation_sessions as other_session', 'other_session.id', '=', 'other.reconciliation_session_id')
            ->join('import_files as other_file', 'other_file.id', '=', 'other.import_file_id')
            ->where('mine.reconciliation_session_id', $session->id)
            ->where('other_session.status', SessionStatus::Processed)
            ->whereDate('other_session.period', '<>', $session->period)
            ->where('other_file.status', ImportFileStatus::Active)
            ->toBase()
            ->selectRaw('mine.id as id, MIN(other.reconciliation_session_id) as original_session_id')
            ->groupBy('mine.id')
            ->pluck('original_session_id', 'id');

        $rows = PaymentEntry::query()
            ->join('import_files', 'import_files.id', '=', 'payment_entries.import_file_id')
            ->where('payment_entries.reconciliation_session_id', $session->id)
            ->where('import_files.status', ImportFileStatus::Active)
            ->orderBy('payment_entries.id')
            ->toBase()
            ->get([
                'payment_entries.id', 'payment_entries.supplier_name', 'payment_entries.amount_cents', 'payment_entries.paid_on',
                'payment_entries.unit', 'payment_entries.identity_key', 'payment_entries.card', 'payment_entries.operation_code',
            ]);

        $payments = [];

        foreach ($rows as $row) {
            if ($excludedCodes->contains($row->operation_code)) {
                $skips[] = $this->skip(SkipReason::ExcludedCode, paymentId: $row->id, operationCode: OperationCode::normalize($row->operation_code));

                continue;
            }

            if (isset($duplicates[$row->id])) {
                $skips[] = $this->skip(SkipReason::DuplicateOfOtherPeriod, paymentId: $row->id, originalSessionId: (int) $duplicates[$row->id]);

                continue;
            }

            $payments[] = new PaymentCandidate(
                id: (int) $row->id,
                supplier: $this->normalizer->normalize($row->supplier_name),
                amountCents: (int) $row->amount_cents,
                paidOn: substr((string) $row->paid_on, 0, 10),
                unit: (string) $row->unit,
                identityKey: (string) $row->identity_key,
                card: $row->card,
            );
        }

        return $payments;
    }

    /**
     * @param  list<array<string, mixed>>  $skips
     * @return list<AuthorizationCandidate>
     */
    protected function sessionAuthorizations(ReconciliationSession $session, EngineParameters $parameters, array &$skips): array
    {
        $duplicates = AuthorizationEntry::query()
            ->from('authorization_entries as mine')
            ->join('authorization_entries as other', 'other.identity_key', '=', 'mine.identity_key')
            ->join('reconciliation_sessions as other_session', 'other_session.id', '=', 'other.reconciliation_session_id')
            ->leftJoin('import_files as other_file', 'other_file.id', '=', 'other.import_file_id')
            ->where('mine.reconciliation_session_id', $session->id)
            ->where('other_session.status', SessionStatus::Processed)
            ->whereDate('other_session.period', '<>', $session->period)
            ->where(fn ($query) => $query->whereNull('other.import_file_id')->orWhere('other_file.status', ImportFileStatus::Active))
            ->toBase()
            ->selectRaw('mine.id as id, MIN(other.reconciliation_session_id) as original_session_id')
            ->groupBy('mine.id')
            ->pluck('original_session_id', 'id');

        $rows = $this->activeAuthorizations()
            ->where('authorization_entries.reconciliation_session_id', $session->id)
            ->orderBy('authorization_entries.id')
            ->toBase()
            ->get($this->authorizationColumns());

        $authorizations = [];

        foreach ($rows as $row) {
            if (isset($duplicates[$row->id])) {
                $skips[] = $this->skip(SkipReason::DuplicateOfOtherPeriod, authorizationId: $row->id, originalSessionId: (int) $duplicates[$row->id]);

                continue;
            }

            if ((int) $row->balance_cents > 0) {
                $authorizations[] = $this->authorizationCandidate($row, $parameters);
            }
        }

        return $authorizations;
    }

    /**
     * Authorizations with a balance in processed sessions of earlier periods inside the window,
     * and in the other sessions of the same period. Those already receiving payments stay in
     * view however old they are.
     *
     * @return list<AuthorizationCandidate>
     */
    protected function priorAuthorizations(ReconciliationSession $session, EngineParameters $parameters): array
    {
        $windowStart = $session->period->copy()->startOfMonth()->subMonths($parameters->lookbackMonths)->toDateString();
        $period = $session->period->toDateString();

        $rows = $this->activeAuthorizations()
            ->join('reconciliation_sessions', 'reconciliation_sessions.id', '=', 'authorization_entries.reconciliation_session_id')
            ->where('authorization_entries.reconciliation_session_id', '<>', $session->id)
            ->where('reconciliation_sessions.status', SessionStatus::Processed)
            ->whereDate('reconciliation_sessions.period', '<=', $period)
            ->where(fn ($query) => $query
                ->whereDate('reconciliation_sessions.period', '>=', $windowStart)
                ->orWhere('authorization_states.links_count', '>', 0))
            ->whereNotExists(fn ($query) => $query
                ->selectRaw('1')
                ->from('reconciliation_skips')
                ->whereColumn('reconciliation_skips.authorization_entry_id', 'authorization_entries.id'))
            ->orderBy('authorization_entries.id')
            ->toBase()
            ->get($this->authorizationColumns());

        $authorizations = [];

        foreach ($rows as $row) {
            if ((int) $row->balance_cents > 0) {
                $authorizations[] = $this->authorizationCandidate($row, $parameters);
            }
        }

        return $authorizations;
    }

    /**
     * Authorizations of an active file, or created during the reconciliation, with their state.
     *
     * @return Builder<AuthorizationEntry>
     */
    protected function activeAuthorizations(): Builder
    {
        return AuthorizationEntry::query()
            ->leftJoin('import_files', 'import_files.id', '=', 'authorization_entries.import_file_id')
            ->leftJoin('authorization_states', 'authorization_states.authorization_entry_id', '=', 'authorization_entries.id')
            ->where(fn ($query) => $query
                ->whereNull('authorization_entries.import_file_id')
                ->orWhere('import_files.status', ImportFileStatus::Active));
    }

    /**
     * @return list<mixed>
     */
    protected function authorizationColumns(): array
    {
        return [
            'authorization_entries.id', 'authorization_entries.supplier_name', 'authorization_entries.amount_cents',
            'authorization_entries.authorized_on', 'authorization_entries.identity_key', 'authorization_entries.payment_method',
            'authorization_entries.card', 'authorization_entries.payment_condition',
            AuthorizationEntry::query()->raw('COALESCE(authorization_states.balance_cents, authorization_entries.amount_cents) as balance_cents'),
        ];
    }

    protected function authorizationCandidate(object $row, EngineParameters $parameters): AuthorizationCandidate
    {
        return new AuthorizationCandidate(
            id: (int) $row->id,
            supplier: $this->normalizer->normalize($row->supplier_name),
            balanceCents: (int) $row->balance_cents,
            authorizedCents: (int) $row->amount_cents,
            authorizedOn: substr((string) $row->authorized_on, 0, 10),
            identityKey: (string) $row->identity_key,
            paysByCard: $parameters->cardMethodMarker !== ''
                && str_contains(strtoupper(Str::ascii((string) $row->payment_method)), $parameters->cardMethodMarker),
            card: $row->card,
        );
    }

    /**
     * @return list<string>
     */
    protected function blockedPairs(): array
    {
        return ReconciliationPairBlock::query()
            ->toBase()
            ->get(['authorization_identity_key', 'payment_unit', 'payment_identity_key'])
            ->map(fn (object $block): string => Matcher::pairKey($block->authorization_identity_key, $block->payment_unit, $block->payment_identity_key))
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    protected function skip(SkipReason $reason, ?int $paymentId = null, ?int $authorizationId = null, ?string $operationCode = null, ?int $originalSessionId = null): array
    {
        return [
            'payment_entry_id' => $paymentId,
            'authorization_entry_id' => $authorizationId,
            'reason' => $reason->value,
            'operation_code' => $operationCode,
            'original_session_id' => $originalSessionId,
        ];
    }
}
