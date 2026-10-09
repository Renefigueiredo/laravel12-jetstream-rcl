<?php

namespace App\Actions\Conciliation;

use App\Enums\AuditAction;
use App\Enums\AuthorizationStatus;
use App\Enums\DifferenceTreatment;
use App\Enums\JustificationCategory;
use App\Models\AuthorizationEntry;
use App\Models\ReconciliationLink;
use App\Models\User;
use App\Services\Audit\AuditRecorder;
use App\Services\Reconciliation\EngineParametersFactory;
use App\Services\Reconciliation\ReconciliationDecisions;
use App\Services\Reconciliation\ReconciliationLinker;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class CloseAuthorizationWithDiscount
{
    public function __construct(
        protected AuditRecorder $audit,
        protected ReconciliationLinker $linker,
        protected ReconciliationDecisions $decisions,
        protected EngineParametersFactory $parameters,
    ) {}

    /**
     * Close what is left of a partially paid authorization as a discount. The discount is always
     * the remaining balance and is recorded on the latest payment linked to it.
     *
     * @throws ActionRefusedException
     */
    public function handle(User $user, AuthorizationEntry $authorization, JustificationCategory $category, string $justification): ReconciliationLink
    {
        if (blank($justification)) {
            throw new ActionRefusedException(__('conciliation.reconciliation.errors.justification_required'));
        }

        if (mb_strlen($justification) > 500) {
            throw new ActionRefusedException(__('conciliation.reconciliation.errors.justification_too_long'));
        }

        return DB::transaction(function () use ($user, $authorization, $category, $justification): ReconciliationLink {
            $authorization = AuthorizationEntry::query()->with('state')->lockForUpdate()->findOrFail($authorization->id);

            if ($authorization->status() !== AuthorizationStatus::Partial) {
                throw new ActionRefusedException(__('conciliation.reconciliation.errors.no_open_balance'));
            }

            $link = ReconciliationLink::query()
                ->with(['payment.session', 'run'])
                ->where('authorization_entry_id', $authorization->id)
                ->latest('id')
                ->lockForUpdate()
                ->firstOrFail();

            Gate::forUser($user)->authorize('view', $link->payment->session);

            $this->decisions->runOf($link->payment);

            $balance = $authorization->balanceCents();

            $link->update([
                'treatment' => DifferenceTreatment::Discount,
                'discount_cents' => $link->discount_cents + $balance,
                'justification_category' => $category,
                'justification' => $justification,
                'decided_by' => $user->id,
                'decided_at' => now(),
            ]);

            $this->linker->refresh($authorization, $this->parameters->fromRun($link->run));

            $this->audit->record(
                $user,
                AuditAction::AuthorizationClosedWithDiscount,
                $link,
                $authorization->supplier_name,
                ['balance_cents' => $balance],
                ['discount_cents' => $balance, 'justification_category' => $category->value, 'justification' => $justification, 'balance_cents' => 0],
            );

            return $link;
        });
    }
}
