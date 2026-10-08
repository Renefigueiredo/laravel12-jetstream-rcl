<?php

namespace Database\Factories;

use App\Enums\UserPermission;
use App\Models\User;
use App\Models\UserPermissionGrant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserPermissionGrant>
 */
class UserPermissionGrantFactory extends Factory
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
            'permission' => UserPermission::ManageExcludedCodes,
            'granted_by' => User::factory()->administrador(),
        ];
    }
}
