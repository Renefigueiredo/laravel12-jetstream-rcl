<?php

namespace Database\Factories;

use App\Enums\ImportAttemptStatus;
use App\Enums\ImportSlot;
use App\Models\ReconciliationSession;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ImportAttempt>
 */
class ImportAttemptFactory extends Factory
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
            'status' => ImportAttemptStatus::Queued,
            'user_id' => User::factory(),
            'original_name' => 'pagamentos.csv',
            'disk' => 'local',
            'path' => 'conciliation/incoming/'.fake()->uuid().'.csv',
            'size_bytes' => 1024,
            'sha256' => hash('sha256', fake()->uuid()),
            'progress' => 0,
            'error_count' => 0,
        ];
    }

    public function withStatus(ImportAttemptStatus $status): static
    {
        return $this->state(fn (array $attributes) => ['status' => $status]);
    }

    /**
     * Indicate that the file passed validation and waits for the period divergence confirmation.
     */
    public function awaitingConfirmation(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ImportAttemptStatus::AwaitingConfirmation,
            'progress' => 50,
            'rows_total' => 10,
            'rows_valid' => 10,
            'rows_skipped_value' => 0,
            'rows_out_of_period' => 4,
            'min_date' => '2026-05-28',
            'max_date' => '2026-06-03',
        ]);
    }
}
