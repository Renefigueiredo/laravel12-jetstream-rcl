<?php

namespace App\Services\Reconciliation;

use App\Enums\AuditAction;
use App\Enums\LinkOrigin;
use App\Enums\MatchClassification;
use App\Enums\ReconciliationRunStatus;
use App\Enums\SuggestionStatus;
use App\Models\AuthorizationEntry;
use App\Models\PaymentEntry;
use App\Models\ReconciliationRun;
use App\Models\ReconciliationSkip;
use App\Models\ReconciliationSuggestion;
use App\Services\Audit\AuditRecorder;
use App\Services\Reconciliation\Matching\EngineParameters;
use App\Services\Reconciliation\Matching\MatchedPair;
use App\Services\Reconciliation\Matching\MatchResult;
use Illuminate\Support\Facades\DB;

class MatchResultWriter
{
    public function __construct(
        protected ReconciliationLinker $linker,
        protected RunTotals $totals,
        protected AuditRecorder $audit,
    ) {}

    /**
     * Persist the whole result of a run, or nothing at all.
     */
    public function write(ReconciliationRun $run, LoadedCandidates $loaded, MatchResult $result, EngineParameters $parameters): void
    {
        DB::transaction(function () use ($run, $loaded, $result, $parameters): void {
            $balances = $loaded->balances();
            $suggestions = $result->suggestions;

            $authorizations = AuthorizationEntry::query()
                ->whereIn('id', array_unique(array_map(fn (MatchedPair $pair): int => $pair->authorizationId, $result->links)))
                ->get()
                ->keyBy('id');

            $payments = PaymentEntry::query()
                ->whereIn('id', array_map(fn (MatchedPair $pair): int => $pair->paymentId, $result->links))
                ->get()
                ->keyBy('id');

            foreach ($result->links as $pair) {
                $authorization = AuthorizationEntry::query()->with('state')->lockForUpdate()->find($pair->authorizationId);

                if ($authorization === null || $authorization->balanceCents() !== $balances[$pair->authorizationId]) {
                    $suggestions[] = $this->asDoubtful($pair);

                    continue;
                }

                $this->linker->link($authorizations[$pair->authorizationId], $payments[$pair->paymentId], new LinkAttributes(
                    run: $run,
                    parameters: $parameters,
                    origin: LinkOrigin::Automatic,
                    isInstallment: $pair->classification === MatchClassification::Installment,
                    engineClassification: $pair->classification,
                    score: $pair->score->score,
                    supplierScore: $pair->score->supplierScore,
                    amountScore: $pair->score->amountScore,
                    paidBeforeAuthorization: $pair->paidBeforeAuthorization,
                    cardMismatch: $pair->cardMismatch,
                ));
            }

            $this->insertSuggestions($run, $suggestions);
            $this->insertSkips($run, $loaded->skips);

            $run->update(['status' => ReconciliationRunStatus::Completed, 'finished_at' => now()]);

            $totals = $this->totals->for($run);

            $run->update(['totals' => $totals]);

            $this->audit->record(
                $run->requester,
                AuditAction::ReconciliationCompleted,
                $run,
                $run->session->label(),
                null,
                [
                    'tolerance_cents' => $run->tolerance_cents,
                    'tolerance_basis_points' => $run->tolerance_basis_points,
                    'tolerance_cap_cents' => $run->tolerance_cap_cents,
                    'surcharge_cap_basis_points' => $run->surcharge_cap_basis_points,
                    'automatic_threshold' => $run->automatic_threshold,
                    'suggestion_threshold' => $run->suggestion_threshold,
                    'supplier_threshold' => $run->supplier_threshold,
                    'lookback_months' => $run->lookback_months,
                    'excluded_codes' => count($run->excluded_codes),
                    'totals' => $totals,
                ],
            );
        });
    }

    /**
     * An authorization changed by someone else while the run was calculating is left to the operator.
     */
    protected function asDoubtful(MatchedPair $pair): MatchedPair
    {
        return new MatchedPair(
            authorizationId: $pair->authorizationId,
            paymentId: $pair->paymentId,
            score: $pair->score,
            classification: MatchClassification::Doubtful,
            paidBeforeAuthorization: $pair->paidBeforeAuthorization,
            cardMismatch: $pair->cardMismatch,
        );
    }

    /**
     * @param  list<MatchedPair>  $suggestions
     */
    protected function insertSuggestions(ReconciliationRun $run, array $suggestions): void
    {
        $now = now();

        $rows = array_map(fn (MatchedPair $pair): array => [
            'reconciliation_run_id' => $run->id,
            'authorization_entry_id' => $pair->authorizationId,
            'payment_entry_id' => $pair->paymentId,
            'classification' => $pair->classification->value,
            'score' => $pair->score->score,
            'supplier_score' => $pair->score->supplierScore,
            'amount_score' => $pair->score->amountScore,
            'difference_cents' => $pair->score->differenceCents,
            'position' => $pair->position,
            'is_tie' => $pair->isTie,
            'paid_before_authorization' => $pair->paidBeforeAuthorization,
            'card_mismatch' => $pair->cardMismatch,
            'status' => SuggestionStatus::Pending->value,
            'created_at' => $now,
            'updated_at' => $now,
        ], $suggestions);

        foreach (array_chunk($rows, (int) config('conciliation.upload.insert_chunk')) as $chunk) {
            ReconciliationSuggestion::query()->insert($chunk);
        }
    }

    /**
     * @param  list<array<string, mixed>>  $skips
     */
    protected function insertSkips(ReconciliationRun $run, array $skips): void
    {
        $rows = array_map(fn (array $skip): array => ['reconciliation_run_id' => $run->id, ...$skip], $skips);

        foreach (array_chunk($rows, (int) config('conciliation.upload.insert_chunk')) as $chunk) {
            ReconciliationSkip::query()->insert($chunk);
        }
    }
}
