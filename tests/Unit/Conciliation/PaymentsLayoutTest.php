<?php

namespace Tests\Unit\Conciliation;

use App\Enums\ImportSlot;
use App\Services\Import\DateParser;
use App\Services\Import\Layouts\PaymentsLayout;
use App\Services\Import\MoneyParser;
use App\Services\Import\ParsedRow;
use Tests\TestCase;

class PaymentsLayoutTest extends TestCase
{
    protected function layout(): PaymentsLayout
    {
        return new PaymentsLayout(new MoneyParser, new DateParser);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function row(array $overrides = []): array
    {
        return [...$this->layout()->exampleRow(), ...$overrides];
    }

    public function test_layout_has_the_24_headers_of_the_erp_report(): void
    {
        $headers = $this->layout()->headers();

        $this->assertCount(24, $headers);
        $this->assertContains('PREV_LIQUIIDACAO', $headers);
        $this->assertSame('VL_RECEBIDO', $this->layout()->amountColumn());
        $this->assertSame('DT_LIQUIDACAO', $this->layout()->periodDateColumn());
    }

    public function test_only_row_mandatory_columns_are_required_headers(): void
    {
        $this->assertEqualsCanonicalizing(
            ['COD_OPERACAO', 'DT_LIQUIDACAO', 'CEDENTE', 'OBRIGACAO', 'VL_RECEBIDO', 'VL_OBRIGACAO'],
            $this->layout()->requiredHeaders(),
        );
    }

    public function test_it_parses_a_valid_row(): void
    {
        $row = $this->row(['VL_RECEBIDO' => '1250.4', 'VL_OBRIGACAO' => '1300', 'DT_LIQUIDACAO' => '31-JUL-26']);

        $parsed = $this->layout()->parse($row, ['CEDENTE' => 'FORNECEDOR EXEMPLO LTDA'], 7, ImportSlot::PaymentsSaude);

        $this->assertInstanceOf(ParsedRow::class, $parsed);
        $this->assertSame(7, $parsed->rowNumber);
        $this->assertSame(125040, $parsed->attributes['amount_cents']);
        $this->assertSame(130000, $parsed->attributes['obligation_amount_cents']);
        $this->assertSame('2026-07-31', $parsed->attributes['paid_on']->format('Y-m-d'));
        $this->assertSame('2026-07-31', $parsed->periodDate->format('Y-m-d'));
        $this->assertSame('saude', $parsed->attributes['unit']);
        $this->assertSame('FORNECEDOR EXEMPLO LTDA', $parsed->attributes['supplier_name']);
        $this->assertSame('20150652', $parsed->attributes['operation_code']);
        $this->assertSame('PIX', $parsed->attributes['transaction_type']);
        $this->assertSame('900001|20150652|700001', $parsed->identityKey);
        $this->assertSame(['CEDENTE' => 'FORNECEDOR EXEMPLO LTDA'], $parsed->raw);
    }

    public function test_unit_comes_from_the_slot(): void
    {
        $parsed = $this->layout()->parse($this->row(), [], 2, ImportSlot::PaymentsSocial);

        $this->assertSame('social', $parsed->attributes['unit']);
    }

    public function test_numeric_cells_are_read_as_text_codes(): void
    {
        $parsed = $this->layout()->parse($this->row(['COD_OPERACAO' => 20150652.0, 'OBRIGACAO' => 900001]), [], 2, ImportSlot::PaymentsSocial);

        $this->assertSame('20150652', $parsed->attributes['operation_code']);
        $this->assertSame('900001', $parsed->attributes['obligation_number']);
    }

    public function test_row_with_zero_or_negative_paid_amount_is_skipped_even_with_other_invalid_fields(): void
    {
        $this->assertNull($this->layout()->parse($this->row(['VL_RECEBIDO' => '0']), [], 2, ImportSlot::PaymentsSocial));
        $this->assertNull($this->layout()->parse($this->row(['VL_RECEBIDO' => '-15.2', 'CEDENTE' => '']), [], 2, ImportSlot::PaymentsSocial));
    }

    public function test_missing_required_fields_are_reported_per_column(): void
    {
        $errors = $this->layout()->parse(
            $this->row(['CEDENTE' => ' ', 'DT_LIQUIDACAO' => '2026-07-31', 'VL_OBRIGACAO' => 'abc']),
            [],
            45,
            ImportSlot::PaymentsSocial,
        );

        $this->assertIsArray($errors);
        $this->assertEqualsCanonicalizing(['CEDENTE', 'DT_LIQUIDACAO', 'VL_OBRIGACAO'], array_map(fn ($error) => $error->column, $errors));
        $this->assertSame([45, 45, 45], array_map(fn ($error) => $error->rowNumber, $errors));
        $this->assertSame('2026-07-31', collect($errors)->firstWhere('column', 'DT_LIQUIDACAO')->value);
    }

    public function test_unreadable_amount_is_an_error(): void
    {
        $errors = $this->layout()->parse($this->row(['VL_RECEBIDO' => 'dez reais']), [], 3, ImportSlot::PaymentsSocial);

        $this->assertIsArray($errors);
        $this->assertSame('VL_RECEBIDO', $errors[0]->column);
        $this->assertSame('conciliation.errors.money.format', $errors[0]->reasonKey);
    }

    public function test_absent_optional_columns_become_empty_fields(): void
    {
        $row = $this->row();
        unset($row['ESPECIE'], $row['CD_MOVIMENTO_CONTA'], $row['DS_AUDIT']);

        $parsed = $this->layout()->parse($row, [], 2, ImportSlot::PaymentsSocial);

        $this->assertInstanceOf(ParsedRow::class, $parsed);
        $this->assertNull($parsed->attributes['species']);
        $this->assertNull($parsed->attributes['account_movement']);
        $this->assertSame('900001|20150652|', $parsed->identityKey);
    }
}
