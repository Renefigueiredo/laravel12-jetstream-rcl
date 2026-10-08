<?php

namespace Database\Factories;

use App\Enums\ImportFileStatus;
use App\Enums\ImportSlot;
use App\Models\ReconciliationSession;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ImportFile>
 */
class ImportFileFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'reconciliation_session_id' => ReconciliationSession::factory(),
            'slot' => ImportSlot::PaymentsSocial,
            'status' => ImportFileStatus::Active,
            'original_name' => 'pagamentos.csv',
            'disk' => 'local',
            'path' => 'conciliation/sessions/'.fake()->uuid().'.csv',
            'size_bytes' => 1024,
            'sha256' => hash('sha256', fake()->uuid()),
            'sheet_count' => 1,
            'rows_imported' => 0,
            'rows_skipped_value' => 0,
            'rows_skipped_existing' => 0,
            'rows_out_of_period' => 0,
            'period_divergence' => false,
            'uploaded_by' => User::factory(),
        ];
    }

    public function forSlot(ImportSlot $slot): static
    {
        return $this->state(fn (array $attributes) => ['slot' => $slot]);
    }

    /**
     * Indicate that the file was replaced by a newer one.
     */
    public function replaced(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ImportFileStatus::Replaced,
            'replaced_at' => now(),
        ]);
    }

    /**
     * Indicate that the file was accepted with entries outside the session period.
     */
    public function withPeriodDivergence(): static
    {
        return $this->state(fn (array $attributes) => [
            'period_divergence' => true,
            'rows_out_of_period' => 3,
            'min_date' => '2026-05-28',
            'max_date' => '2026-06-03',
            'divergence_confirmed_by' => User::factory(),
            'divergence_confirmed_at' => now(),
        ]);
    }
}
