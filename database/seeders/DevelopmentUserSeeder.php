<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DevelopmentUserSeeder extends Seeder
{
    /**
     * Seed one administrator and one operator for local development.
     */
    public function run(): void
    {
        User::factory()->administrador()->create([
            'name' => 'Administrador',
            'email' => 'admin@example.com',
        ]);

        User::factory()->create([
            'name' => 'Operador',
            'email' => 'operador@example.com',
        ]);
    }
}
