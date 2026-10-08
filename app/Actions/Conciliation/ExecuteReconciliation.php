<?php

namespace App\Actions\Conciliation;

use App\Enums\AuditAction;
use App\Enums\ImportSlot;
use App\Enums\SessionStatus;
use App\Jobs\RunReconciliation;
use App\Models\ReconciliationSession;
use App\Models\User;
use App\Services\Audit\AuditRecorder;
use App\Services\Import\SessionLockedException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class ExecuteReconciliation
{
    public function __construct(protected AuditRecorder $audit) {}

    /**
     * Lock the session and queue its reconciliation.
     *
     * @throws ActionRefusedException
     */
    public function handle(User $user, ReconciliationSession $session): void
    {
        Gate::forUser($user)->authorize('execute', $session);

        if (! config('conciliation.engine_enabled')) {
            throw new ActionRefusedException(__('conciliation.sessions.execute.engine_disabled'));
        }

        DB::transaction(function () use ($user, $session): void {
            $session = ReconciliationSession::query()->lockForUpdate()->findOrFail($session->id);

            if (! $session->isOpen()) {
                throw new ActionRefusedException(SessionLockedException::for($session)->getMessage());
            }

            $missingSlots = $session->missingSlots();

            if ($missingSlots !== []) {
                throw new ActionRefusedException(__('conciliation.sessions.execute.missing', [
                    'slots' => implode(', ', array_map(fn (ImportSlot $slot): string => $slot->label(), $missingSlots)),
                ]));
            }

            $session->update([
                'status' => SessionStatus::Processing,
                'processing_started_at' => now(),
                'progress' => 0,
                'last_failure' => null,
            ]);

            $this->audit->record(
                $user,
                AuditAction::ReconciliationRequested,
                $session,
                $session->label(),
                ['status' => SessionStatus::Open->value],
                [
                    'status' => SessionStatus::Processing->value,
                    'import_file_ids' => $session->activeFiles->pluck('id', 'slot.value')->all(),
                ],
            );
        });

        RunReconciliation::dispatch($session->id);
    }
}
