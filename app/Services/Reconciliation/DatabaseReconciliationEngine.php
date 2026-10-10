<?php

namespace App\Services\Reconciliation;

use App\Contracts\ReconciliationEngine;
use App\Enums\AuditAction;
use App\Enums\ReconciliationRunStatus;
use App\Models\AuditLog;
use App\Models\ReconciliationLink;
use App\Models\ReconciliationRun;
use App\Models\ReconciliationSession;
use App\Models\ReconciliationSkip;
use App\Models\ReconciliationSuggestion;
use App\Services\ExcludedCodes\ExcludedOperationCodes;
use App\Services\Reconciliation\Matching\EngineParameters;
use App\Services\Reconciliation\Matching\Matcher;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class DatabaseReconciliationEngine implements ReconciliationEngine
{
    public const LOCK_NAME = 'conciliation:engine';

    public function __construct(
        protected EngineParametersFactory $parameters,
        protected ExcludedOperationCodes $excludedCodes,
        protected CandidateLoader $loader,
        protected Matcher $matcher,
        protected MatchResultWriter $writer,
        protected AuthorizationStateCalculator $states,
        protected InstallmentForecaster $forecaster,
    ) {}

    /**
     * Run the reconciliation of a session. One run at a time in the whole system: two runs
     * could otherwise settle the same earlier authorization with different payments.
     */
    public function run(ReconciliationSession $session, callable $reportProgress): void
    {
        $lock = Cache::lock(self::LOCK_NAME, 3600);

        try {
            $lock->block((int) config('conciliation.engine.lock_wait_seconds'));
        } catch (LockTimeoutException) {
            throw new RuntimeException(__('conciliation.reconciliation.errors.engine_busy'));
        }

        $run = null;

        try {
            $parameters = $this->parameters->fromSettings();
            $excludedCodes = $this->excludedCodes->snapshot();
            $run = $this->startRun($session, $parameters, $excludedCodes->codes());

            $reportProgress(5);

            $loaded = $this->loader->load($session, $parameters, $excludedCodes);

            $reportProgress(15);

            $result = $this->matcher->match(
                $loaded->authorizations,
                $loaded->payments,
                $loaded->blockedPairs,
                $parameters,
                fn (int $percent) => $reportProgress(15 + intdiv($percent * 70, 100)),
            );

            $this->writer->write($run, $loaded, $result, $parameters);

            $reportProgress(95);
        } catch (Throwable $exception) {
            $run?->update(['status' => ReconciliationRunStatus::Failed, 'finished_at' => now()]);

            throw $exception;
        } finally {
            $lock->release();
        }
    }

    /**
     * Remove what the engine produced for the session. Links carrying a human decision stay:
     * the session cannot be reopened while they exist.
     */
    public function discardResult(ReconciliationSession $session): void
    {
        DB::transaction(function () use ($session): void {
            $runIds = ReconciliationRun::query()
                ->where('reconciliation_session_id', $session->id)
                ->whereIn('status', [ReconciliationRunStatus::Running, ReconciliationRunStatus::Completed])
                ->pluck('id');

            if ($runIds->isEmpty()) {
                return;
            }

            $links = ReconciliationLink::query()->whereIn('reconciliation_run_id', $runIds)->whereNull('decided_by');
            $authorizationIds = (clone $links)->distinct()->pluck('authorization_entry_id');

            $links->delete();

            ReconciliationLink::query()
                ->whereIn('authorization_entry_id', $authorizationIds)
                ->where('tolerance_writeoff_cents', '>', 0)
                ->update(['tolerance_writeoff_cents' => 0, 'updated_at' => now()]);

            foreach ($authorizationIds as $authorizationId) {
                $this->states->recalculate($authorizationId);
            }

            ReconciliationSuggestion::query()->whereIn('reconciliation_run_id', $runIds)->delete();
            ReconciliationSkip::query()->whereIn('reconciliation_run_id', $runIds)->delete();

            ReconciliationRun::query()->whereIn('id', $runIds)->update([
                'status' => ReconciliationRunStatus::Discarded,
                'updated_at' => now(),
            ]);

            $this->forecaster->refreshAll();
        });
    }

    /**
     * @param  list<string>  $excludedCodes
     */
    protected function startRun(ReconciliationSession $session, EngineParameters $parameters, array $excludedCodes): ReconciliationRun
    {
        $requestedBy = AuditLog::query()
            ->where('action', AuditAction::ReconciliationRequested)
            ->where('auditable_type', $session->getMorphClass())
            ->where('auditable_id', $session->id)
            ->latest('id')
            ->value('user_id');

        return ReconciliationRun::query()->create([
            'reconciliation_session_id' => $session->id,
            'status' => ReconciliationRunStatus::Running,
            'requested_by' => $requestedBy ?? $session->created_by,
            'tolerance_cents' => $parameters->toleranceCents,
            'tolerance_basis_points' => $parameters->toleranceBasisPoints,
            'tolerance_cap_cents' => $parameters->toleranceCapCents,
            'surcharge_cap_basis_points' => $parameters->surchargeCapBasisPoints,
            'automatic_threshold' => $parameters->automaticThreshold,
            'suggestion_threshold' => $parameters->suggestionThreshold,
            'supplier_threshold' => $parameters->supplierThreshold,
            'lookback_months' => $parameters->lookbackMonths,
            'excluded_codes' => $excludedCodes,
            'started_at' => now(),
        ]);
    }
}
