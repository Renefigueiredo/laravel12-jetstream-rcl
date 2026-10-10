<?php

namespace App\Services\Reconciliation;

use App\Actions\Conciliation\ActionRefusedException;
use App\Enums\ImportFileStatus;
use App\Enums\SessionStatus;
use App\Models\AuthorizationEntry;
use App\Models\PaymentEntry;
use App\Models\ReconciliationSession;
use App\Services\Reconciliation\Matching\EngineParameters;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;

/**
 * Which authorizations may receive a payment: the active ones with a balance, of the session of
 * the payment, or of an earlier processed session inside the window of months, or already
 * receiving payments however old they are.
 */
class AuthorizationAvailability
{
    /**
     * @throws ActionRefusedException
     */
    public function assertCanReceive(AuthorizationEntry $authorization, PaymentEntry $payment, EngineParameters $parameters): void
    {
        if ($authorization->import_file_id !== null && $authorization->importFile->status !== ImportFileStatus::Active) {
            throw new ActionRefusedException(__('conciliation.reconciliation.errors.authorization_not_available'));
        }

        if ($authorization->balanceCents() === 0) {
            throw new ActionRefusedException(__('conciliation.reconciliation.errors.authorization_settled'));
        }

        if ($authorization->reconciliation_session_id === $payment->reconciliation_session_id) {
            return;
        }

        $session = $authorization->session;
        $paymentPeriod = $payment->session->period;

        $available = $session->status === SessionStatus::Processed
            && $session->period->lte($paymentPeriod)
            && ($session->period->gte($this->windowStart($payment, $parameters)) || ($authorization->state?->links_count ?? 0) > 0);

        if (! $available) {
            throw new ActionRefusedException(__('conciliation.reconciliation.errors.authorization_not_available'));
        }
    }

    /**
     * The authorizations a payment may be linked to, by the same rule the link action checks.
     *
     * @return Builder<AuthorizationEntry>
     */
    public function forPayment(PaymentEntry $payment, EngineParameters $parameters): Builder
    {
        $period = $payment->session->period->toDateString();
        $windowStart = $this->windowStart($payment, $parameters)->toDateString();

        return AuthorizationEntry::query()
            ->where(fn (Builder $query) => $query
                ->whereNull('authorization_entries.import_file_id')
                ->orWhereHas('importFile', fn (Builder $query) => $query->where('status', ImportFileStatus::Active)))
            ->whereDoesntHave('state', fn (Builder $query) => $query->where('balance_cents', 0))
            ->whereNotExists(fn ($query) => $query
                ->selectRaw('1')
                ->from('reconciliation_skips')
                ->whereColumn('reconciliation_skips.authorization_entry_id', 'authorization_entries.id'))
            ->where(fn (Builder $query) => $query
                ->where('authorization_entries.reconciliation_session_id', $payment->reconciliation_session_id)
                ->orWhere(fn (Builder $query) => $query
                    ->whereIn('authorization_entries.reconciliation_session_id', ReconciliationSession::query()
                        ->where('status', SessionStatus::Processed)
                        ->whereDate('period', '<=', $period)
                        ->select('id'))
                    ->where(fn (Builder $query) => $query
                        ->whereIn('authorization_entries.reconciliation_session_id', ReconciliationSession::query()
                            ->whereDate('period', '>=', $windowStart)
                            ->select('id'))
                        ->orWhereHas('state', fn (Builder $query) => $query->where('links_count', '>', 0)))));
    }

    protected function windowStart(PaymentEntry $payment, EngineParameters $parameters): CarbonInterface
    {
        return $payment->session->period->copy()->startOfMonth()->subMonths($parameters->lookbackMonths);
    }
}
