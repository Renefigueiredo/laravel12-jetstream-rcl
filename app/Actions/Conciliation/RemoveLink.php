<?php

namespace App\Actions\Conciliation;

use App\Enums\AuditAction;
use App\Enums\PairBlockReason;
use App\Enums\SuggestionStatus;
use App\Models\ReconciliationLink;
use App\Models\ReconciliationSuggestion;
use App\Models\User;
use App\Services\Audit\AuditRecorder;
use App\Services\Reconciliation\EngineParametersFactory;
use App\Services\Reconciliation\ReconciliationDecisions;
use App\Services\Reconciliation\ReconciliationLinker;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class RemoveLink
{
    public function __construct(
        protected AuditRecorder $audit,
        protected ReconciliationLinker $linker,
        protected ReconciliationDecisions $decisions,
        protected EngineParametersFactory $parameters,
    ) {}

    /**
     * Undo a reconciled pair. A pair the operator had confirmed returns as the suggestion it was,
     * to be decided again. A pair the engine linked on its own, or one linked by hand, returns
     * as two separate entries and the engine does not pair them again.
     *
     * @throws ActionRefusedException
     */
    public function handle(User $user, ReconciliationLink $link): void
    {
        DB::transaction(function () use ($user, $link): void {
            $link = ReconciliationLink::query()
                ->with(['authorization', 'payment.session', 'run', 'decider'])
                ->lockForUpdate()
                ->find($link->id);

            if ($link === null) {
                throw new ActionRefusedException(__('conciliation.reconciliation.errors.link_already_removed'));
            }

            Gate::forUser($user)->authorize('view', $link->payment->session);

            $this->decisions->runOf($link->payment);

            $authorization = $link->authorization;

            $before = [
                ...$this->decisions->describe($link),
                'decided_by_name' => $link->decider?->name,
                'authorization_supplier' => $authorization->supplier_name,
                'payment_supplier' => $link->payment->supplier_name,
                'payment_cents' => $link->payment->amount_cents,
            ];

            $parameters = $this->parameters->fromRun($link->run);

            $confirmed = ReconciliationSuggestion::query()
                ->where('authorization_entry_id', $link->authorization_entry_id)
                ->where('payment_entry_id', $link->payment_entry_id)
                ->where('status', SuggestionStatus::Confirmed)
                ->latest('id')
                ->first();

            $this->linker->unlink($link, $parameters);

            if ($confirmed !== null) {
                $confirmed->update(['status' => SuggestionStatus::Pending, 'decided_by' => null, 'decided_at' => null]);
                $this->linker->refresh($authorization, $parameters);
            } else {
                $this->decisions->block($authorization, $link->payment, PairBlockReason::Unlinked, $user);
            }

            $authorization = $authorization->fresh(['state']);
            $after = ['balance_cents' => $authorization->balanceCents(), 'status' => $authorization->status()->value];

            if ($authorization->isCreatedInReconciliation() && $authorization->state === null) {
                $authorization->delete();
                $after['authorization_deleted'] = true;
            }

            $this->audit->record($user, AuditAction::LinkRemoved, $link, $before['authorization_supplier'], $before, $after);
        });
    }
}
