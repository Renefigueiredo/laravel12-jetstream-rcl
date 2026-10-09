<?php

namespace Database\Factories;

use App\Enums\DifferenceType;
use App\Enums\LinkOrigin;
use App\Enums\MatchClassification;
use App\Models\AuthorizationEntry;
use App\Models\PaymentEntry;
use App\Models\ReconciliationLink;
use App\Models\ReconciliationRun;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReconciliationLink>
 */
class ReconciliationLinkFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'authorization_entry_id' => AuthorizationEntry::factory(),
            'payment_entry_id' => PaymentEntry::factory(),
            'reconciliation_run_id' => ReconciliationRun::factory(),
            'origin' => LinkOrigin::Automatic,
            'engine_classification' => MatchClassification::Automatic,
            'score' => 100,
            'supplier_score' => 100,
            'amount_score' => 100,
            'difference_type' => DifferenceType::Exact,
        ];
    }

    public function manual(): static
    {
        return $this->state(fn (array $attributes) => [
            'origin' => LinkOrigin::Manual,
            'decided_by' => User::factory(),
            'decided_at' => now(),
        ]);
    }
}
