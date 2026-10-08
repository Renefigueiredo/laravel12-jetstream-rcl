<?php

namespace Tests\Unit\Conciliation;

use App\Enums\ImportSlot;
use App\Services\Import\DateParser;
use App\Services\Import\Layouts\AuthorizationsLayout;
use App\Services\Import\MoneyParser;
use App\Services\Import\ParsedRow;
use DateTimeImmutable;
use Tests\TestCase;

class AuthorizationsLayoutTest extends TestCase
{
    protected function layout(): AuthorizationsLayout
    {
        return new AuthorizationsLayout(new MoneyParser, new DateParser);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function row(array $overrides = []): array
    {
        return [...$this->layout()->exampleRow(), ...$overrides];
    }

    public function test_layout_has_the_11_headers_of_the_elo_spreadsheet(): void
    {
        $this->assertCount(11, $this->layout()->headers());
        $this->assertSame('VALOR', $this->layout()->amountColumn());
        $this->assertSame('MAP_DATA_AUTORIZACAO', $this->layout()->periodDateColumn());
    }

    public function test_only_row_mandatory_columns_are_required_headers(): void
    {
        $this->assertEqualsCanonicalizing(
            ['MAP_SOLICITACAO', 'MAP_FORNECEDOR', 'VALOR', 'MAP_DATA_AUTORIZACAO'],
            $this->layout()->requiredHeaders(),
        );
    }

    public function test_it_parses_a_valid_row(): void
    {
        $parsed = $this->layout()->parse(
            $this->row(['VALOR' => 'R$ 3.895,73', 'MAP_DATA_AUTORIZACAO' => '27/07/2026', 'MAP_AFRAFEP_CARTAO' => 5352]),
            [],
            2,
            ImportSlot::Authorizations,
        );

        $this->assertInstanceOf(ParsedRow::class, $parsed);
        $this->assertSame(389573, $parsed->attributes['amount_cents']);
        $this->assertSame('2026-07-27', $parsed->attributes['authorized_on']->format('Y-m-d'));
        $this->assertSame('MATERIAL DE ESCRITORIO - 00001', $parsed->attributes['request']);
        $this->assertSame('5352', $parsed->attributes['card']);
        $this->assertSame('PIX', $parsed->attributes['payment_method']);
        $this->assertArrayNotHasKey('unit', $parsed->attributes);
        $this->assertSame(64, strlen($parsed->identityKey));
    }

    public function test_native_cells_are_read_by_value(): void
    {
        $parsed = $this->layout()->parse(
            $this->row(['VALOR' => 3895.73, 'MAP_DATA_AUTORIZACAO' => new DateTimeImmutable('2026-07-27')]),
            [],
            2,
            ImportSlot::Authorizations,
        );

        $this->assertSame(389573, $parsed->attributes['amount_cents']);
        $this->assertSame('2026-07-27', $parsed->attributes['authorized_on']->format('Y-m-d'));
    }

    public function test_identity_ignores_case_and_spaces_but_not_the_request(): void
    {
        $first = $this->layout()->parse($this->row(), [], 2, ImportSlot::Authorizations);
        $sameWithNoise = $this->layout()->parse($this->row(['MAP_FORNECEDOR' => '  fornecedor exemplo ltda ']), [], 3, ImportSlot::Authorizations);
        $otherRequest = $this->layout()->parse($this->row(['MAP_SOLICITACAO' => 'MATERIAL DE ESCRITORIO - 00002']), [], 4, ImportSlot::Authorizations);
        $otherAmount = $this->layout()->parse($this->row(['VALOR' => 'R$ 1.250,41']), [], 5, ImportSlot::Authorizations);

        $this->assertSame($first->identityKey, $sameWithNoise->identityKey);
        $this->assertNotSame($first->identityKey, $otherRequest->identityKey);
        $this->assertNotSame($first->identityKey, $otherAmount->identityKey);
    }

    public function test_row_with_zero_value_is_skipped(): void
    {
        $this->assertNull($this->layout()->parse($this->row(['VALOR' => 'R$ 0,00', 'MAP_FORNECEDOR' => '']), [], 2, ImportSlot::Authorizations));
    }

    public function test_missing_required_fields_are_errors(): void
    {
        $errors = $this->layout()->parse($this->row(['MAP_SOLICITACAO' => '', 'MAP_DATA_AUTORIZACAO' => '']), [], 9, ImportSlot::Authorizations);

        $this->assertIsArray($errors);
        $this->assertEqualsCanonicalizing(['MAP_SOLICITACAO', 'MAP_DATA_AUTORIZACAO'], array_map(fn ($error) => $error->column, $errors));
        $this->assertSame('conciliation.errors.date.empty', collect($errors)->firstWhere('column', 'MAP_DATA_AUTORIZACAO')->reasonKey);
    }
}
