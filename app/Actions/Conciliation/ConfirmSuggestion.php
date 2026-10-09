<?php

namespace App\Actions\Conciliation;

use App\Enums\AuditAction;
use App\Enums\LinkOrigin;
use App\Enums\SuggestionStatus;
use App\Models\ReconciliationLink;
use App\Models\ReconciliationSuggestion;
use App\Models\User;
use App\Services\Audit\AuditRecorder;
use App\Services\Reconciliation\DifferenceDecision;
use App\Services\Reconciliation\EngineParametersFactory;
use App\Services\Reconciliation\LinkAttributes;
use App\Services\Reconciliation\ReconciliationDecisions;
use App\Services\Reconciliation\ReconciliationLinker;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class ConfirmSuggestion
{
    public function __construct(
        protected AuditRecorder $audit,
        protected ReconciliationLinker $linker,
        protected ReconciliationDecisions $decisions,
        protected EngineParametersFactory $parameters,
    ) {}

    /**
     * Confirm a pair the engine suggested: the two entries are linked as a manual reconciliation.
     *
     * @throws ActionRefusedException
     */
    public function handle(User $user, ReconciliationSuggestion $suggestion, ?DifferenceDecision $decision = null): ReconciliationLink
    {
        return DB::transaction(function () use ($user, $suggestion, $decision): ReconciliationLink {
            $suggestion = ReconciliationSuggestion::query()
                ->with(['authorization.state', 'payment.importFile', 'payment.session', 'run'])
                ->lockForUpdate()
                ->find($suggestion->id);

            if ($suggestion === null || $suggestion->status !== SuggestionStatus::Pending) {
                throw new ActionRefusedException(__('conciliation.reconciliation.errors.suggestion_not_pending'));
            }

            Gate::forUser($user)->authorize('view', $suggestion->payment->session);

            $run = $this->decisions->runOf($suggestion->payment);
            $this->decisions->assertLinkable($suggestion->payment);

            $parameters = $this->parameters->fromRun($run);
            $authorization = $suggestion->authorization;

            [$type, $difference] = $this->decisions->compare($authorization->balanceCents(), $suggestion->payment->amount_cents, $parameters);

            $this->decisions->assertDecisionFits($type, $difference, $authorization->amount_cents, $decision, $parameters);

            $before = [
                'classification' => $suggestion->classification->value,
                'score' => $suggestion->score,
                'supplier_score' => $suggestion->supplier_score,
                'amount_score' => $suggestion->amount_score,
                'difference_cents' => $suggestion->difference_cents,
                'balance_cents' => $authorization->balanceCents(),
            ];

            $suggestion->update(['status' => SuggestionStatus::Confirmed, 'decided_by' => $user->id, 'decided_at' => now()]);

            $link = $this->linker->link($authorization, $suggestion->payment, new LinkAttributes(
                run: $run,
                parameters: $parameters,
                origin: LinkOrigin::Manual,
                engineClassification: $suggestion->classification,
                score: $suggestion->score,
                supplierScore: $suggestion->supplier_score,
                amountScore: $suggestion->amount_score,
                treatment: $decision?->treatment,
                justificationCategory: $decision?->category,
                justification: $decision?->justification,
                paidBeforeAuthorization: $suggestion->paid_before_authorization,
                cardMismatch: $suggestion->card_mismatch,
                decidedBy: $user,
            ));

            $this->audit->record(
                $user,
                AuditAction::SuggestionConfirmed,
                $link,
                $authorization->supplier_name,
                $before,
                [...$this->decisions->describe($link), 'balance_cents' => $authorization->fresh()->balanceCents()],
            );

            return $link;
        });
    }
}
