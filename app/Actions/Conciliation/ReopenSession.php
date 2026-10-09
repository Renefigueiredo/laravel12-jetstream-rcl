<?php

namespace App\Actions\Conciliation;

use App\Contracts\ReconciliationEngine;
use App\Contracts\ReconciliationResultInspector;
use App\Enums\AuditAction;
use App\Enums\SessionStatus;
use App\Models\ReconciliationSession;
use App\Models\User;
use App\Services\Audit\AuditRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class ReopenSession
{
    public function __construct(
        protected AuditRecorder $audit,
        protected ReconciliationResultInspector $inspector,
        protected ReconciliationEngine $engine,
    ) {}

    /**
     * Return a processed session to the open status so that a spreadsheet can be replaced.
     *
     * @throws ActionRefusedException
     */
    public function handle(User $user, ReconciliationSession $session): void
    {
        Gate::forUser($user)->authorize('reopen', $session);

        DB::transaction(function () use ($user, $session): void {
            $session = ReconciliationSession::query()->lockForUpdate()->findOrFail($session->id);

            if ($session->status !== SessionStatus::Processed) {
                throw new ActionRefusedException(__('conciliation.sessions.reopen.not_processed'));
            }

            $blockingDecisions = $this->inspector->blockingDecisionCount($session);

            if ($blockingDecisions > 0) {
                throw new ActionRefusedException(trans_choice('conciliation.sessions.reopen.blocked', $blockingDecisions, ['count' => $blockingDecisions]));
            }

            $this->engine->discardResult($session);

            $session->update(['status' => SessionStatus::Open, 'result_stale' => true, 'progress' => null]);

            $this->audit->record(
                $user,
                AuditAction::SessionReopened,
                $session,
                $session->label(),
                ['status' => SessionStatus::Processed->value],
                ['status' => SessionStatus::Open->value],
            );
        });
    }
}
