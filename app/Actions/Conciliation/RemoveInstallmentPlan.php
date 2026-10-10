<?php

namespace App\Actions\Conciliation;

use App\Enums\AuditAction;
use App\Models\AuthorizationEntry;
use App\Models\InstallmentPlan;
use App\Models\User;
use App\Services\Audit\AuditRecorder;
use App\Services\Reconciliation\InstallmentForecaster;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class RemoveInstallmentPlan
{
    public function __construct(
        protected AuditRecorder $audit,
        protected InstallmentForecaster $forecaster,
    ) {}

    /**
     * Remove the plan of instalments of an authorization; the payment condition of the
     * spreadsheet is the hint again. No link and no balance change.
     *
     * @throws ActionRefusedException
     */
    public function handle(User $user, AuthorizationEntry $authorization): void
    {
        DB::transaction(function () use ($user, $authorization): void {
            $authorization = AuthorizationEntry::query()->with('session')->lockForUpdate()->findOrFail($authorization->id);

            Gate::forUser($user)->authorize('view', $authorization->session);

            $plan = InstallmentPlan::query()->with('items')->where('authorization_identity_key', $authorization->identity_key)->lockForUpdate()->first();

            if ($plan === null) {
                throw new ActionRefusedException(__('conciliation.dashboard.plan.errors.missing'));
            }

            $this->audit->record($user, AuditAction::InstallmentPlanRemoved, $plan, $authorization->supplier_name, [
                'authorization_entry_id' => $authorization->id,
                'installments' => $plan->toSchedule(),
            ], null);

            $plan->delete();

            $this->forecaster->refresh($authorization->unsetRelation('plan'));
        });
    }
}
