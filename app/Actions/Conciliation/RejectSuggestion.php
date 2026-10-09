<?php

namespace App\Actions\Conciliation;

use App\Enums\AuditAction;
use App\Enums\PairBlockReason;
use App\Enums\SuggestionStatus;
use App\Models\ReconciliationSuggestion;
use App\Models\User;
use App\Services\Audit\AuditRecorder;
use App\Services\Reconciliation\ReconciliationDecisions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class RejectSuggestion
{
    public function __construct(
        protected AuditRecorder $audit,
        protected ReconciliationDecisions $decisions,
    ) {}

    /**
     * Reject a pair the engine suggested; the engine never suggests it again.
     *
     * @throws ActionRefusedException
     */
    public function handle(User $user, ReconciliationSuggestion $suggestion): void
    {
        DB::transaction(function () use ($user, $suggestion): void {
            $suggestion = ReconciliationSuggestion::query()
                ->with(['authorization', 'payment.session'])
                ->lockForUpdate()
                ->find($suggestion->id);

            if ($suggestion === null || $suggestion->status !== SuggestionStatus::Pending) {
                throw new ActionRefusedException(__('conciliation.reconciliation.errors.suggestion_not_pending'));
            }

            Gate::forUser($user)->authorize('view', $suggestion->payment->session);

            $this->decisions->runOf($suggestion->payment);

            $suggestion->update(['status' => SuggestionStatus::Rejected, 'decided_by' => $user->id, 'decided_at' => now()]);

            $this->decisions->block($suggestion->authorization, $suggestion->payment, PairBlockReason::Rejected, $user);

            $this->audit->record(
                $user,
                AuditAction::SuggestionRejected,
                $suggestion,
                $suggestion->authorization->supplier_name,
                [
                    'status' => SuggestionStatus::Pending->value,
                    'authorization_entry_id' => $suggestion->authorization_entry_id,
                    'payment_entry_id' => $suggestion->payment_entry_id,
                    'classification' => $suggestion->classification->value,
                    'score' => $suggestion->score,
                ],
                ['status' => SuggestionStatus::Rejected->value],
            );
        });
    }
}
