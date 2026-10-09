<?php

namespace App\Actions\Conciliation;

use App\Enums\AuditAction;
use App\Models\ReconciliationSettings;
use App\Models\User;
use App\Services\Audit\AuditRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class UpdateReconciliationSettings
{
    public const MAX_TOLERANCE_CENTS = 100_000;

    public const MAX_BASIS_POINTS = 10_000;

    public function __construct(protected AuditRecorder $audit) {}

    /**
     * Change the tolerance and the surcharge cap used by the runs started from now on.
     *
     * What was already processed keeps the values it ran with.
     *
     * @param  int  $toleranceCents  Fixed tolerance; zero when only the percentage applies
     * @param  int|null  $toleranceBasisPoints  Percentage tolerance in hundredths of a percent; null for none
     * @param  int|null  $toleranceCapCents  Most the percentage may allow; null for no cap
     * @param  int  $surchargeCapBasisPoints  Most an accepted surcharge may add to the authorized amount
     *
     * @throws ActionRefusedException
     */
    public function handle(User $user, int $toleranceCents, ?int $toleranceBasisPoints, ?int $toleranceCapCents, int $surchargeCapBasisPoints): ReconciliationSettings
    {
        Gate::forUser($user)->authorize('configure-tolerance');

        $toleranceBasisPoints = $toleranceBasisPoints === 0 ? null : $toleranceBasisPoints;
        $toleranceCapCents = $toleranceBasisPoints === null || $toleranceCapCents === 0 ? null : $toleranceCapCents;

        if ($toleranceCents < 0 || $toleranceCents > self::MAX_TOLERANCE_CENTS) {
            throw new ActionRefusedException(__('conciliation.settings.errors.tolerance_amount'));
        }

        if ($toleranceBasisPoints !== null && ($toleranceBasisPoints < 0 || $toleranceBasisPoints > self::MAX_BASIS_POINTS)) {
            throw new ActionRefusedException(__('conciliation.settings.errors.tolerance_percent'));
        }

        if ($toleranceCapCents !== null && $toleranceCapCents < 0) {
            throw new ActionRefusedException(__('conciliation.settings.errors.tolerance_cap'));
        }

        if ($surchargeCapBasisPoints < 0 || $surchargeCapBasisPoints > self::MAX_BASIS_POINTS) {
            throw new ActionRefusedException(__('conciliation.settings.errors.surcharge_cap'));
        }

        return DB::transaction(function () use ($user, $toleranceCents, $toleranceBasisPoints, $toleranceCapCents, $surchargeCapBasisPoints): ReconciliationSettings {
            $settings = ReconciliationSettings::query()->lockForUpdate()->findOrFail(ReconciliationSettings::current()->id);

            $before = $settings->only(['tolerance_cents', 'tolerance_basis_points', 'tolerance_cap_cents', 'surcharge_cap_basis_points']);

            $settings->fill([
                'tolerance_cents' => $toleranceCents,
                'tolerance_basis_points' => $toleranceBasisPoints,
                'tolerance_cap_cents' => $toleranceCapCents,
                'surcharge_cap_basis_points' => $surchargeCapBasisPoints,
            ]);

            if (! $settings->isDirty()) {
                return $settings;
            }

            $settings->updated_by = $user->id;
            $settings->save();

            $this->audit->record(
                $user,
                AuditAction::ReconciliationSettingsChanged,
                $settings,
                __('conciliation.settings.title'),
                $before,
                $settings->only(array_keys($before)),
            );

            return $settings;
        });
    }
}
