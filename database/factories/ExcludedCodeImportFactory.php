<?php

namespace Database\Factories;

use App\Enums\ExcludedCodeImportStatus;
use App\Models\ExcludedCodeImport;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ExcludedCodeImport>
 */
class ExcludedCodeImportFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->administrador(),
            'status' => ExcludedCodeImportStatus::Queued,
            'original_name' => 'codigos.csv',
            'disk' => 'local',
            'path' => 'conciliation/excluded-codes/'.Str::uuid().'.csv',
            'size_bytes' => 120,
        ];
    }

    public function queued(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ExcludedCodeImportStatus::Queued,
        ]);
    }

    public function processing(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ExcludedCodeImportStatus::Processing,
            'started_at' => now(),
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ExcludedCodeImportStatus::Completed,
            'started_at' => now(),
            'finished_at' => now(),
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ExcludedCodeImportStatus::Rejected,
            'errors' => [['row' => 2, 'column' => 'COD_OPERACAO', 'reason' => __('conciliation.excluded_codes.import.row_errors.code_blank')]],
            'started_at' => now(),
            'finished_at' => now(),
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ExcludedCodeImportStatus::Failed,
            'failure_message' => __('conciliation.excluded_codes.import.failed'),
            'finished_at' => now(),
        ]);
    }
}
