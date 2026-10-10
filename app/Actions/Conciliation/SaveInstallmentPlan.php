<?php

namespace App\Actions\Conciliation;

use App\Enums\AuditAction;
use App\Models\AuthorizationEntry;
use App\Models\InstallmentPlan;
use App\Models\User;
use App\Services\Audit\AuditRecorder;
use App\Services\Reconciliation\EngineParametersFactory;
use App\Services\Reconciliation\InstallmentForecaster;
use App\Support\Money;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Throwable;

class SaveInstallmentPlan
{
    public const MAX_INSTALLMENTS = 120;

    public function __construct(
        protected AuditRecorder $audit,
        protected EngineParametersFactory $parameters,
        protected InstallmentForecaster $forecaster,
    ) {}

    /**
     * Inform, or replace, the instalments an authorization is paid in when they are not equal.
     *
     * The plan is a hint for the next runs: it changes no link and no balance.
     *
     * @param  list<array{amount_cents: int, expected_month?: string|null}>  $installments
     *
     * @throws ActionRefusedException
     */
    public function handle(User $user, AuthorizationEntry $authorization, array $installments): InstallmentPlan
    {
        $items = $this->validated($installments);

        return DB::transaction(function () use ($user, $authorization, $items): InstallmentPlan {
            $authorization = AuthorizationEntry::query()->with(['state', 'session'])->lockForUpdate()->findOrFail($authorization->id);

            Gate::forUser($user)->authorize('view', $authorization->session);

            if ($authorization->balanceCents() === 0) {
                throw new ActionRefusedException(__('conciliation.dashboard.plan.errors.settled'));
            }

            $difference = array_sum(array_column($items, 'amount_cents')) - $authorization->amount_cents;

            if (abs($difference) > $this->parameters->fromSettings()->toleranceFor($authorization->amount_cents)) {
                throw new ActionRefusedException(__('conciliation.dashboard.plan.errors.sum', [
                    'difference' => Money::format(abs($difference)),
                    'direction' => __('conciliation.dashboard.plan.errors.'.($difference > 0 ? 'above' : 'below')),
                ]));
            }

            $linked = $authorization->state?->links_count ?? 0;

            if (count($items) < $linked) {
                throw new ActionRefusedException(trans_choice('conciliation.dashboard.plan.errors.fewer_than_paid', $linked, ['count' => $linked]));
            }

            $plan = InstallmentPlan::query()->with('items')->where('authorization_identity_key', $authorization->identity_key)->lockForUpdate()->first();
            $before = $plan?->toSchedule();

            if ($plan === null) {
                $plan = InstallmentPlan::query()->create([
                    'authorization_identity_key' => $authorization->identity_key,
                    'created_by' => $user->id,
                ]);
            } else {
                $plan->items()->delete();
                $plan->update(['updated_by' => $user->id]);
                $plan->touch();
            }

            foreach ($items as $index => $item) {
                $plan->items()->create(['position' => $index + 1, ...$item]);
            }

            $plan->load('items');

            $this->audit->record($user, AuditAction::InstallmentPlanSaved, $plan, $authorization->supplier_name, $before === null ? null : ['installments' => $before], [
                'authorization_entry_id' => $authorization->id,
                'installments' => $plan->toSchedule(),
            ]);

            $this->forecaster->refresh($authorization);

            return $plan;
        });
    }

    /**
     * @param  list<array{amount_cents: int, expected_month?: string|null}>  $installments
     * @return list<array{amount_cents: int, expected_month: string|null}>
     *
     * @throws ActionRefusedException
     */
    protected function validated(array $installments): array
    {
        if ($installments === [] || count($installments) > self::MAX_INSTALLMENTS) {
            throw new ActionRefusedException(__('conciliation.dashboard.plan.errors.count', ['max' => self::MAX_INSTALLMENTS]));
        }

        $items = [];

        foreach (array_values($installments) as $installment) {
            $amount = $installment['amount_cents'] ?? 0;

            if (! is_int($amount) || $amount <= 0) {
                throw new ActionRefusedException(__('conciliation.dashboard.plan.errors.amount'));
            }

            $items[] = ['amount_cents' => $amount, 'expected_month' => $this->month($installment['expected_month'] ?? null)];
        }

        return $items;
    }

    /**
     * @throws ActionRefusedException
     */
    protected function month(?string $month): ?string
    {
        if (blank($month)) {
            return null;
        }

        try {
            if (preg_match('/^\d{4}-\d{2}(-\d{2})?$/', (string) $month) !== 1) {
                throw new ActionRefusedException(__('conciliation.dashboard.plan.errors.month'));
            }

            $date = new DateTimeImmutable(substr((string) $month, 0, 7).'-01');
        } catch (ActionRefusedException $exception) {
            throw $exception;
        } catch (Throwable) {
            throw new ActionRefusedException(__('conciliation.dashboard.plan.errors.month'));
        }

        if ($date->format('Y-m') !== substr((string) $month, 0, 7)) {
            throw new ActionRefusedException(__('conciliation.dashboard.plan.errors.month'));
        }

        return $date->format('Y-m-d');
    }
}
