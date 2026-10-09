<?php

namespace App\Actions\Conciliation;

use App\Enums\AuditAction;
use App\Enums\LinkOrigin;
use App\Models\AuthorizationEntry;
use App\Models\PaymentEntry;
use App\Models\ReconciliationLink;
use App\Models\User;
use App\Services\Audit\AuditRecorder;
use App\Services\Reconciliation\EngineParametersFactory;
use App\Services\Reconciliation\LinkAttributes;
use App\Services\Reconciliation\ReconciliationDecisions;
use App\Services\Reconciliation\ReconciliationLinker;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class CreateMatchingAuthorization
{
    public const MAX_JUSTIFICATION_LENGTH = 500;

    public function __construct(
        protected AuditRecorder $audit,
        protected ReconciliationLinker $linker,
        protected ReconciliationDecisions $decisions,
        protected EngineParametersFactory $parameters,
    ) {}

    /**
     * Regularize a payment made without a registered authorization: create one with the supplier,
     * amount and date of the payment, marked as created in the reconciliation, and link the two.
     *
     * Only an administrator may do it, and the reason given stays on the authorization.
     *
     * @throws ActionRefusedException
     */
    public function handle(User $user, PaymentEntry $payment, string $justification): ReconciliationLink
    {
        Gate::forUser($user)->authorize('create-matching-authorization');

        $justification = trim($justification);

        if ($justification === '') {
            throw new ActionRefusedException(__('conciliation.reconciliation.errors.creation_justification_required'));
        }

        if (mb_strlen($justification) > self::MAX_JUSTIFICATION_LENGTH) {
            throw new ActionRefusedException(__('conciliation.reconciliation.errors.justification_too_long'));
        }

        return DB::transaction(function () use ($user, $payment, $justification): ReconciliationLink {
            $payment = PaymentEntry::query()->with(['importFile', 'session'])->lockForUpdate()->findOrFail($payment->id);

            Gate::forUser($user)->authorize('view', $payment->session);

            $run = $this->decisions->runOf($payment);
            $this->decisions->assertLinkable($payment);

            $authorization = AuthorizationEntry::query()->create([
                'import_file_id' => null,
                'reconciliation_session_id' => $payment->reconciliation_session_id,
                'row_number' => 0,
                'request' => $justification,
                'supplier_name' => $payment->supplier_name,
                'amount_cents' => $payment->amount_cents,
                'authorized_on' => $payment->paid_on,
                'card' => $payment->card,
                'identity_key' => hash('sha256', 'created-in-reconciliation|'.$payment->identity_key),
                'raw' => [],
                'created_by' => $user->id,
                'source_payment_entry_id' => $payment->id,
            ]);

            $link = $this->linker->link($authorization, $payment, new LinkAttributes(
                run: $run,
                parameters: $this->parameters->fromRun($run),
                origin: LinkOrigin::Manual,
                decidedBy: $user,
            ));

            $this->audit->record(
                $user,
                AuditAction::AuthorizationCreatedInReconciliation,
                $authorization,
                $authorization->supplier_name,
                null,
                [
                    'payment_entry_id' => $payment->id,
                    'amount_cents' => $authorization->amount_cents,
                    'authorized_on' => $authorization->authorized_on->toDateString(),
                    'justification' => $justification,
                    'reconciliation_link_id' => $link->id,
                ],
            );

            return $link;
        });
    }
}
