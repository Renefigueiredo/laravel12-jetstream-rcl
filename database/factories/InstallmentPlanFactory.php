<?php

namespace Database\Factories;

use App\Models\InstallmentPlan;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InstallmentPlan>
 */
class InstallmentPlanFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'authorization_identity_key' => hash('sha256', fake()->uuid()),
            'created_by' => User::factory(),
        ];
    }

    /**
     * @param  list<int>  $amounts  Amount of each instalment, in cents
     */
    public function withInstallments(array $amounts): static
    {
        return $this->afterCreating(function (InstallmentPlan $plan) use ($amounts): void {
            foreach (array_values($amounts) as $index => $amount) {
                $plan->items()->create(['position' => $index + 1, 'amount_cents' => $amount]);
            }
        });
    }
}
