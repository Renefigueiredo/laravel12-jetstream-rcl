<?php

namespace Database\Factories;

use App\Models\ImportFile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PaymentEntry>
 */
class PaymentEntryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $obligation = fake()->unique()->numerify('9#####');
        $movement = fake()->unique()->numerify('7#####');

        return [
            'import_file_id' => ImportFile::factory(),
            'reconciliation_session_id' => fn (array $attributes) => ImportFile::query()->findOrFail($attributes['import_file_id'])->reconciliation_session_id,
            'row_number' => fake()->unique()->numberBetween(2, 1000000),
            'unit' => fn (array $attributes) => ImportFile::query()->findOrFail($attributes['import_file_id'])->slot->unit()?->value ?? 'social',
            'supplier_name' => mb_strtoupper(fake()->company()),
            'amount_cents' => fake()->numberBetween(100, 500000),
            'obligation_amount_cents' => fn (array $attributes) => $attributes['amount_cents'],
            'paid_on' => '2026-05-15',
            'operation_code' => '20150652',
            'operation_name' => 'MATERIAL DE CONSUMO',
            'species' => 'NOTA FISCAL',
            'transaction_type' => 'PIX',
            'obligation_number' => $obligation,
            'source_document' => null,
            'settlement_status' => 'LIQUIDADO',
            'account_movement' => $movement,
            'identity_key' => $obligation.'|20150652|'.$movement,
            'raw' => [],
        ];
    }
}
