<?php

namespace App\Services\Reconciliation;

use App\Models\ReconciliationRun;
use App\Models\ReconciliationSettings;
use App\Services\Reconciliation\Matching\EngineParameters;

class EngineParametersFactory
{
    /**
     * The parameters in effect now, for a run that is about to start.
     */
    public function fromSettings(): EngineParameters
    {
        $settings = ReconciliationSettings::current();

        return new EngineParameters(
            toleranceCents: $settings->tolerance_cents,
            toleranceBasisPoints: $settings->tolerance_basis_points,
            toleranceCapCents: $settings->tolerance_cap_cents,
            surchargeCapBasisPoints: $settings->surcharge_cap_basis_points,
            automaticThreshold: (int) config('conciliation.engine.automatic_threshold'),
            suggestionThreshold: (int) config('conciliation.engine.suggestion_threshold'),
            supplierThreshold: (int) config('conciliation.engine.supplier_threshold'),
            lookbackMonths: (int) config('conciliation.engine.lookback_months'),
            suggestionsPerAuthorization: (int) config('conciliation.engine.suggestions_per_authorization'),
            cardMethodMarker: (string) config('conciliation.engine.card_method_marker'),
        );
    }

    /**
     * The parameters a run was executed with; later configuration changes do not alter them.
     *
     * The surcharge cap is the exception: it is the one in effect when the decision is taken.
     */
    public function fromRun(ReconciliationRun $run): EngineParameters
    {
        return new EngineParameters(
            toleranceCents: $run->tolerance_cents,
            toleranceBasisPoints: $run->tolerance_basis_points,
            toleranceCapCents: $run->tolerance_cap_cents,
            surchargeCapBasisPoints: ReconciliationSettings::current()->surcharge_cap_basis_points,
            automaticThreshold: $run->automatic_threshold,
            suggestionThreshold: $run->suggestion_threshold,
            supplierThreshold: $run->supplier_threshold,
            lookbackMonths: $run->lookback_months,
            suggestionsPerAuthorization: (int) config('conciliation.engine.suggestions_per_authorization'),
            cardMethodMarker: (string) config('conciliation.engine.card_method_marker'),
        );
    }
}
