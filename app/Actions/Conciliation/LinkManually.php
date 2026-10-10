<?php

namespace App\Actions\Conciliation;

use App\Enums\AuditAction;
use App\Enums\DifferenceTreatment;
use App\Enums\DifferenceType;
use App\Enums\LinkOrigin;
use App\Models\AuthorizationEntry;
use App\Models\PaymentEntry;
use App\Models\ReconciliationLink;
use App\Models\User;
use App\Services\Audit\AuditRecorder;
use App\Services\Reconciliation\AuthorizationAvailability;
use App\Services\Reconciliation\DifferenceDecision;
use App\Services\Reconciliation\EngineParametersFactory;
use App\Services\Reconciliation\LinkAttributes;
use App\Services\Reconciliation\ReconciliationDecisions;
use App\Services\Reconciliation\ReconciliationLinker;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class LinkManually
{
    public function __construct(
        protected AuditRecorder $audit,
        protected ReconciliationLinker $linker,
        protected ReconciliationDecisions $decisions,
        protected EngineParametersFactory $parameters,
        protected AuthorizationAvailability $availability,
    ) {}

    /**
     * Link one or more payments of a session to an authorization the engine did not pair them with.
     *
     * Several payments make up one purchase, as when a marketplace order is charged by each seller.
     * Only the last one may leave a difference to be decided.
     *
     * @param  list<PaymentEntry>  $payments
     * @return list<ReconciliationLink>
     *
     * @throws ActionRefusedException
     */
    public function handle(User $user, AuthorizationEntry $authorization, array $payments, ?DifferenceDecision $decision = null): array
    {
        if ($payments === []) {
            throw new ActionRefusedException(__('conciliation.reconciliation.errors.no_payment_selected'));
        }

        return DB::transaction(function () use ($user, $authorization, $payments, $decision): array {
            $authorization = AuthorizationEntry::query()->with(['state', 'session', 'importFile'])->lockForUpdate()->findOrFail($authorization->id);

            $payments = PaymentEntry::query()
                ->with(['importFile', 'session'])
                ->whereIn('id', array_map(fn (PaymentEntry $payment): int => $payment->id, $payments))
                ->orderBy('paid_on')
                ->orderBy('id')
                ->get();

            if ($payments->pluck('reconciliation_session_id')->unique()->count() !== 1) {
                throw new ActionRefusedException(__('conciliation.reconciliation.errors.payments_of_one_session'));
            }

            Gate::forUser($user)->authorize('view', $payments->first()->session);

            $run = $this->decisions->runOf($payments->first());
            $parameters = $this->parameters->fromRun($run);

            $this->availability->assertCanReceive($authorization, $payments->first(), $parameters);

            $links = [];
            $last = $payments->count() - 1;

            foreach ($payments->values() as $index => $payment) {
                $this->decisions->assertLinkable($payment);

                $balance = $authorization->fresh()->balanceCents();

                if ($balance === 0) {
                    throw new ActionRefusedException(__('conciliation.reconciliation.errors.authorization_settled'));
                }

                [$type, $difference] = $this->decisions->compare($balance, $payment->amount_cents, $parameters);

                if ($index < $last) {
                    if ($type !== DifferenceType::Partial) {
                        throw new ActionRefusedException(__('conciliation.reconciliation.errors.too_many_payments'));
                    }

                    $applied = new DifferenceDecision(DifferenceTreatment::StillOwed);
                } else {
                    $this->decisions->assertDecisionFits($type, $difference, $authorization->amount_cents, $decision, $parameters);

                    $applied = $type === DifferenceType::Exact ? null : $decision;
                }

                $link = $this->linker->link($authorization, $payment, new LinkAttributes(
                    run: $run,
                    parameters: $parameters,
                    origin: LinkOrigin::Manual,
                    treatment: $applied?->treatment,
                    justificationCategory: $applied?->category,
                    justification: $applied?->justification,
                    paidBeforeAuthorization: $payment->paid_on->lt($authorization->authorized_on),
                    cardMismatch: $authorization->card !== null && $payment->card !== null && $authorization->card !== $payment->card,
                    decidedBy: $user,
                ));

                $this->audit->record(
                    $user,
                    AuditAction::ManualLinkCreated,
                    $link,
                    $authorization->supplier_name,
                    ['balance_cents' => $balance],
                    [...$this->decisions->describe($link), 'balance_cents' => $authorization->fresh()->balanceCents()],
                );

                $links[] = $link;
            }

            return $links;
        });
    }
}
