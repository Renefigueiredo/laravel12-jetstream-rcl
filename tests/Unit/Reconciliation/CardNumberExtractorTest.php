<?php

namespace Tests\Unit\Reconciliation;

use App\Services\Reconciliation\Matching\CardNumberExtractor;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class CardNumberExtractorTest extends TestCase
{
    #[DataProvider('species')]
    public function test_card_is_read_from_the_species(?string $species, ?string $expected): void
    {
        $this->assertSame($expected, CardNumberExtractor::extract($species, 'FATURA CARTAO'));
    }

    /**
     * @return array<string, array{string|null, string|null}>
     */
    public static function species(): array
    {
        return [
            'plain' => ['FATURA CARTAO 0798', '0798'],
            'additional card in parentheses' => ['FATURA CARTAO 7607 (7613)', '7607'],
            'unit after the digits' => ['FATURA CARTAO 4931 SOCIAL', '4931'],
            'accents and case' => ['Fatura Cartão 5352 (8347)', '5352'],
            'extra spaces' => ['  FATURA   CARTAO   0798  ', '0798'],
            'no digits' => ['FATURA CARTAO', null],
            'two digits only' => ['FATURA CARTAO 79', null],
            'digits before the marker only' => ['0798 FATURA CARTAO', null],
            'other species' => ['NOTA FISCAL FORNECED', null],
            'empty' => ['', null],
            'null' => [null, null],
        ];
    }
}
