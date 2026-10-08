<?php

namespace Database\Factories;

use App\Enums\ImportSlot;
use App\Models\ImportFile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\AuthorizationEntry>
 */
class AuthorizationEntryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'import_file_id' => ImportFile::factory()->forSlot(ImportSlot::Authorizations),
            'reconciliation_session_id' => fn (array $attributes) => ImportFile::query()->findOrFail($attributes['import_file_id'])->reconciliation_session_id,
            'row_number' => fake()->unique()->numberBetween(2, 1000000),
            'request' => 'MATERIAL DE ESCRITORIO - '.fake()->unique()->numerify('#####'),
            'supplier_name' => mb_strtoupper(fake()->company()),
            'amount_cents' => fake()->numberBetween(100, 500000),
            'authorized_on' => '2026-05-15',
            'payment_method' => 'PIX',
            'card' => null,
            'payment_condition' => 'A vista',
            'identity_key' => hash('sha256', fake()->uuid()),
            'raw' => [],
        ];
    }
}
