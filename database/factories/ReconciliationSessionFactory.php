<?php

namespace Database\Factories;

use App\Enums\ImportSlot;
use App\Enums\SessionStatus;
use App\Models\ImportFile;
use App\Models\ReconciliationSession;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ReconciliationSession>
 */
class ReconciliationSessionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'period' => '2026-05-01',
            'status' => SessionStatus::Open,
            'created_by' => User::factory(),
            'result_stale' => false,
        ];
    }

    /**
     * Indicate that the reconciliation of the session is running.
     */
    public function processing(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => SessionStatus::Processing,
            'processing_started_at' => now(),
            'progress' => 0,
        ]);
    }

    /**
     * Indicate that the session has been reconciled.
     */
    public function processed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => SessionStatus::Processed,
            'first_processed_at' => now(),
            'processing_started_at' => now(),
            'processed_at' => now(),
        ]);
    }

    /**
     * Indicate that the session was reconciled and then reopened.
     */
    public function reopened(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => SessionStatus::Open,
            'first_processed_at' => now(),
            'processed_at' => now(),
            'result_stale' => true,
        ]);
    }

    /**
     * Give the session an active file in each of the three slots.
     */
    public function withActiveFiles(): static
    {
        return $this->afterCreating(function (ReconciliationSession $session): void {
            foreach (ImportSlot::cases() as $slot) {
                ImportFile::factory()->forSlot($slot)->create([
                    'reconciliation_session_id' => $session->id,
                    'uploaded_by' => $session->created_by,
                ]);
            }
        });
    }
}
