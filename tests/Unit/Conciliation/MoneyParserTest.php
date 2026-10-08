<?php

namespace Tests\Unit\Conciliation;

use App\Services\Import\CellParseException;
use App\Services\Import\MoneyParser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class MoneyParserTest extends TestCase
{
    /**
     * @return array<string, array{mixed, int}>
     */
    public static function acceptedValues(): array
    {
        return [
            'brazilian with thousands' => ['1.234,56', 123456],
            'international with thousands' => ['1,234.56', 123456],
            'dot decimal' => ['1234.56', 123456],
            'comma decimal' => ['1234,56', 123456],
            'one decimal digit' => ['4.1', 410],
            'one decimal digit with comma' => ['4,1', 410],
            'leading dot' => ['.8', 80],
            'leading comma' => [',8', 80],
            'leading dot two digits' => ['.13', 13],
            'single dot with three digits is thousands' => ['1.234', 123400],
            'single comma with three digits is thousands' => ['1,234', 123400],
            'no separator' => ['1234', 123400],
            'repeated thousands separator' => ['1.234.567', 123456700],
            'repeated thousands with decimal' => ['1.234.567,89', 123456789],
            'currency symbol' => ['R$ 3.895,73', 389573],
            'currency symbol without space' => ['R$50,00', 5000],
            'non-breaking space after symbol' => ["R$\u{00A0}430,00", 43000],
            'surrounding spaces' => ['  240,80  ', 24080],
            'zero' => ['0', 0],
            'zero with decimals' => ['0,00', 0],
            'negative' => ['-10,00', -1000],
            'negative with symbol' => ['-R$ 10,00', -1000],
            'native integer' => [1234, 123400],
            'native float' => [3895.73, 389573],
            'native float with binary noise' => [0.1 + 0.2, 30],
            'native negative float' => [-12.5, -1250],
            'numeric string from csv' => ['12229.76', 1222976],
        ];
    }

    #[DataProvider('acceptedValues')]
    public function test_it_converts_accepted_values_to_cents(mixed $input, int $expectedCents): void
    {
        $this->assertSame($expectedCents, (new MoneyParser)->parse($input));
    }

    /**
     * @return array<string, array{mixed, string}>
     */
    public static function rejectedValues(): array
    {
        return [
            'empty string' => ['', 'conciliation.errors.money.empty'],
            'only spaces' => ['   ', 'conciliation.errors.money.empty'],
            'null' => [null, 'conciliation.errors.money.empty'],
            'only currency symbol' => ['R$', 'conciliation.errors.money.empty'],
            'letters' => ['abc', 'conciliation.errors.money.format'],
            'letters mixed with digits' => ['12a,50', 'conciliation.errors.money.format'],
            'four decimal digits' => ['1,2345', 'conciliation.errors.money.decimals'],
            'four decimal digits with dot' => ['12.3456', 'conciliation.errors.money.decimals'],
            'three decimals after thousands' => ['1.234,567', 'conciliation.errors.money.decimals'],
            'broken grouping' => ['1.2.3', 'conciliation.errors.money.format'],
            'trailing separator' => ['12,', 'conciliation.errors.money.format'],
            'only separator' => [',', 'conciliation.errors.money.format'],
            'leading separator with three digits' => ['.800', 'conciliation.errors.money.decimals'],
            'native float with three decimals' => [1.234, 'conciliation.errors.money.decimals'],
            'boolean' => [true, 'conciliation.errors.money.format'],
        ];
    }

    #[DataProvider('rejectedValues')]
    public function test_it_rejects_invalid_values_with_a_reason(mixed $input, string $expectedReason): void
    {
        try {
            (new MoneyParser)->parse($input);
            $this->fail('The value should have been rejected.');
        } catch (CellParseException $exception) {
            $this->assertSame($expectedReason, $exception->reasonKey);
        }
    }
}
