<?php

namespace Database\Factories;

use App\Enums\MatchClassification;
use App\Enums\SuggestionStatus;
use App\Models\AuthorizationEntry;
use App\Models\PaymentEntry;
use App\Models\ReconciliationRun;
use App\Models\ReconciliationSuggestion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReconciliationSuggestion>
 */
class ReconciliationSuggestionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'reconciliation_run_id' => ReconciliationRun::factory(),
            'authorization_entry_id' => AuthorizationEntry::factory(),
            'payment_entry_id' => PaymentEntry::factory(),
            'classification' => MatchClassification::Doubtful,
            'score' => 75,
            'supplier_score' => 75,
            'amount_score' => 100,
            'difference_cents' => 0,
            'position' => 1,
            'status' => SuggestionStatus::Pending,
        ];
    }
}
