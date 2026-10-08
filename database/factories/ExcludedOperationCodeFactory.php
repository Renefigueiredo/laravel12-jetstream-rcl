<?php

namespace Database\Factories;

use App\Enums\ExcludedCodeSource;
use App\Models\ExcludedOperationCode;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExcludedOperationCode>
 */
class ExcludedOperationCodeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => (string) fake()->unique()->numberBetween(10000000, 99999999),
            'description' => fake()->sentence(3),
            'source' => ExcludedCodeSource::Manual,
            'created_by' => User::factory()->administrador(),
        ];
    }
}
