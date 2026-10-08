<?php

namespace Database\Factories;

use App\Enums\AuditAction;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\AuditLog>
 */
class AuditLogFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'action' => AuditAction::SessionCreated,
            'auditable_type' => 'reconciliation_session',
            'auditable_id' => fake()->numberBetween(1, 1000),
            'label' => 'Sessão 1 - 05/2026',
            'before' => null,
            'after' => ['status' => 'open'],
        ];
    }
}
