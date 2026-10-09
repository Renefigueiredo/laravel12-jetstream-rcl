<?php

namespace Database\Factories;

use App\Enums\ReconciliationRunStatus;
use App\Models\ReconciliationRun;
use App\Models\ReconciliationSession;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReconciliationRun>
 */
class ReconciliationRunFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'reconciliation_session_id' => ReconciliationSession::factory()->processed(),
            'status' => ReconciliationRunStatus::Completed,
            'requested_by' => User::factory(),
            'tolerance_cents' => 50,
            'tolerance_basis_points' => null,
            'tolerance_cap_cents' => null,
            'surcharge_cap_basis_points' => 1000,
            'automatic_threshold' => 90,
            'suggestion_threshold' => 60,
            'supplier_threshold' => 90,
            'lookback_months' => 3,
            'excluded_codes' => [],
            'totals' => null,
            'started_at' => now(),
            'finished_at' => now(),
        ];
    }

    public function discarded(): static
    {
        return $this->state(fn (array $attributes) => ['status' => ReconciliationRunStatus::Discarded]);
    }
}
