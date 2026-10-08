<?php

namespace App\Actions\Conciliation;

use App\Enums\AuditAction;
use App\Enums\ImportAttemptStatus;
use App\Jobs\PersistImportAttempt;
use App\Models\ImportAttempt;
use App\Models\ReconciliationSession;
use App\Models\User;
use App\Services\Audit\AuditRecorder;
use App\Services\Import\SessionLockedException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class ConfirmPeriodDivergence
{
    public function __construct(protected AuditRecorder $audit) {}

    /**
     * Accept a validated file whose entries fall outside the period of the session.
     *
     * @throws ActionRefusedException
     */
    public function handle(User $user, ImportAttempt $attempt): void
    {
        Gate::forUser($user)->authorize('upload', $attempt->session);

        DB::transaction(function () use ($user, $attempt): void {
            $session = ReconciliationSession::query()->lockForUpdate()->findOrFail($attempt->reconciliation_session_id);

            if (! $session->isOpen()) {
                throw new ActionRefusedException(SessionLockedException::for($session)->getMessage());
            }

            $attempt = ImportAttempt::query()->lockForUpdate()->findOrFail($attempt->id);

            if ($attempt->status !== ImportAttemptStatus::AwaitingConfirmation) {
                throw new ActionRefusedException(__('conciliation.import.divergence.not_pending'));
            }

            $attempt->update([
                'status' => ImportAttemptStatus::Persisting,
                'divergence_confirmed_by' => $user->id,
                'divergence_confirmed_at' => now(),
            ]);

            $this->audit->record($user, AuditAction::PeriodDivergenceConfirmed, $session, $session->label(), null, [
                'slot' => $attempt->slot->value,
                'file' => $attempt->original_name,
                'rows_out_of_period' => $attempt->rows_out_of_period,
                'min_date' => $attempt->min_date?->toDateString(),
                'max_date' => $attempt->max_date?->toDateString(),
            ]);
        });

        PersistImportAttempt::dispatch($attempt->id);
    }
}
