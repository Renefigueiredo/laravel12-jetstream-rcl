<?php

namespace Tests\Unit;

use App\Support\Money;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class MoneyTest extends TestCase
{
    #[DataProvider('typedAmounts')]
    public function test_an_amount_typed_by_a_person_becomes_cents(?string $text, ?int $cents): void
    {
        $this->assertSame($cents, Money::parse($text));
    }

    /**
     * @return array<string, array{0: string|null, 1: int|null}>
     */
    public static function typedAmounts(): array
    {
        return [
            'com vírgula' => ['288,70', 28870],
            'uma casa' => ['288,7', 28870],
            'inteiro' => ['288', 28800],
            'com milhar' => ['1.234,56', 123456],
            'milhar sem centavos' => ['12.000', 1200000],
            'com símbolo' => ['R$ 54,00', 5400],
            'com ponto decimal' => ['288.70', 28870],
            'zero' => ['0', 0],
            'texto' => ['padaria', null],
            'texto com número' => ['pedido 123', null],
            'três casas' => ['1,234', null],
            'vazio' => ['', null],
            'nulo' => [null, null],
            'negativo' => ['-10,00', null],
        ];
    }

    public function test_cents_are_shown_as_reais(): void
    {
        $this->assertSame('R$ 1.250,40', Money::format(125040));
        $this->assertSame('-R$ 0,50', Money::format(-50));
        $this->assertSame('', Money::format(null));
    }
}
