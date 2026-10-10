<?php

namespace App\Services\Reconciliation;

use App\Enums\ImportFileStatus;
use App\Enums\InstallmentStatus;
use App\Enums\ReconciliationRunStatus;
use App\Models\AuthorizationEntry;
use App\Models\AuthorizationForecast;
use App\Models\ReconciliationLink;
use App\Models\ReconciliationRun;
use App\Models\ReconciliationSession;
use App\Services\Reconciliation\Matching\EngineParameters;
use App\Services\Reconciliation\Matching\InstallmentSchedule;
use App\Services\Reconciliation\Matching\PaymentConditionParser;
use App\Services\Reconciliation\Matching\ScheduledInstallment;
use Illuminate\Database\Eloquent\Builder;

class InstallmentForecaster
{
    public function __construct(
        protected InstallmentSchedule $schedule,
        protected EngineParametersFactory $parameters,
        protected PaymentConditionParser $conditions,
    ) {}

    /**
     * The instalments foreseen for an authorization, with the payment that took each one.
     *
     * Load `links.payment` and `plan.items` beforehand when reading a list, and pass the month and
     * the parameters read once.
     *
     * @return list<ScheduledInstallment>
     */
    public function installments(AuthorizationEntry $authorization, ?string $latestProcessedMonth = null, ?EngineParameters $parameters = null): array
    {
        return $this->schedule->for(
            $authorization->amount_cents,
            $authorization->authorized_on->toDateString(),
            $authorization->payment_condition,
            $authorization->plan?->toSchedule(),
            $authorization->links->map(fn (ReconciliationLink $link): array => [
                'id' => $link->payment_entry_id,
                'amount_cents' => $link->payment->amount_cents,
                'paid_on' => $link->payment->paid_on->toDateString(),
            ])->values()->all(),
            $latestProcessedMonth ?? $this->latestProcessedMonth(),
            $parameters ?? $this->parameters->fromSettings(),
        );
    }

    /**
     * Store again what is foreseen for one authorization; nothing is kept when it has no instalments.
     */
    public function refresh(AuthorizationEntry $authorization, ?string $latestProcessedMonth = null, ?EngineParameters $parameters = null): ?AuthorizationForecast
    {
        if (! $this->mayHaveInstallments($authorization)) {
            AuthorizationForecast::query()->whereKey($authorization->id)->delete();

            return null;
        }

        $authorization->load(['links.payment', 'plan.items', 'state']);

        $installments = $this->installments($authorization, $latestProcessedMonth, $parameters);

        if ($installments === []) {
            AuthorizationForecast::query()->whereKey($authorization->id)->delete();

            return null;
        }

        $settled = $authorization->balanceCents() === 0;
        $unpaid = array_values(array_filter($installments, fn (ScheduledInstallment $installment): bool => $installment->status !== InstallmentStatus::Paid));

        return AuthorizationForecast::query()->updateOrCreate(
            ['authorization_entry_id' => $authorization->id],
            [
                'expected_count' => count($installments),
                'paid_count' => count($installments) - count($unpaid),
                'overdue_count' => $settled ? 0 : count(array_filter($unpaid, fn (ScheduledInstallment $installment): bool => $installment->status === InstallmentStatus::Overdue)),
                'next_expected_month' => $settled ? null : ($unpaid[0]->expectedMonth ?? null),
                'from_plan' => $authorization->plan !== null,
            ],
        );
    }

    /**
     * Rebuild what is foreseen for every authorization that still has something to pay, and for
     * the ones that had a forecast. Needed whenever the latest processed month changes.
     *
     * A session counts from the moment its run is concluded, which is when this is called for
     * it: its status only becomes "processed" a moment later.
     */
    public function refreshAll(): void
    {
        $latest = $this->latestProcessedMonth() ?? '';
        $parameters = $this->parameters->fromSettings();

        AuthorizationEntry::query()
            ->where(fn (Builder $query) => $query
                ->whereIn('authorization_entries.id', AuthorizationForecast::query()->select('authorization_entry_id'))
                ->orWhere(fn (Builder $query) => $query
                    ->whereIn('authorization_entries.reconciliation_session_id', ReconciliationRun::query()
                        ->where('status', ReconciliationRunStatus::Completed)
                        ->select('reconciliation_session_id'))
                    ->where(fn (Builder $query) => $query
                        ->whereNull('authorization_entries.import_file_id')
                        ->orWhereHas('importFile', fn (Builder $query) => $query->where('status', ImportFileStatus::Active)))
                    ->whereDoesntHave('state', fn (Builder $query) => $query->where('balance_cents', 0))))
            ->with(['links.payment', 'plan.items', 'state'])
            ->chunkById(500, function ($authorizations) use ($latest, $parameters): void {
                foreach ($authorizations as $authorization) {
                    $this->refresh($authorization, $latest, $parameters);
                }
            }, 'authorization_entries.id', 'id');
    }

    /**
     * Most authorizations are paid at once: they are told apart without reading their links.
     */
    protected function mayHaveInstallments(AuthorizationEntry $authorization): bool
    {
        return ($this->conditions->installments($authorization->payment_condition) ?? 0) > 1
            || $authorization->plan()->exists();
    }

    /**
     * First day of the latest month that has a session with a concluded run.
     */
    public function latestProcessedMonth(): ?string
    {
        $period = ReconciliationSession::query()
            ->whereHas('runs', fn ($query) => $query->where('status', ReconciliationRunStatus::Completed))
            ->max('period');

        return $period === null ? null : substr((string) $period, 0, 7).'-01';
    }
}
