<?php

namespace Tests\Unit\Reconciliation;

use App\Services\Reconciliation\Matching\PaymentConditionParser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PaymentConditionParserTest extends TestCase
{
    #[DataProvider('conditions')]
    public function test_condition_gives_the_foreseen_instalments(?string $condition, ?int $installments): void
    {
        $this->assertSame($installments, (new PaymentConditionParser)->installments($condition));
    }

    public function test_condition_gives_the_terms_in_days_when_it_has_them(): void
    {
        $parser = new PaymentConditionParser;

        $this->assertSame([30, 60, 90], $parser->termDays('30/60/90 dias'));
        $this->assertSame([30, 60], $parser->termDays('30 / 60'));
        $this->assertSame([30], $parser->termDays('30 dias'));
        $this->assertNull($parser->termDays('3x'));
        $this->assertNull($parser->termDays('A vista'));
        $this->assertNull($parser->termDays('488,02'));
        $this->assertNull($parser->termDays(null));
    }

    /**
     * @return array<string, array{0: string|null, 1: int|null}>
     */
    public static function conditions(): array
    {
        return [
            'a vista' => ['A vista', 1],
            'à vista em maiúsculas' => ['À VISTA', 1],
            'a vista em minúsculas' => ['a vista', 1],
            'uma vez' => ['1x', 1],
            'uma vez com espaço' => ['1 X', 1],
            'duas vezes' => ['2x', 2],
            'dez vezes' => ['10X', 10],
            'três vezes com espaço' => ['3 x', 3],
            'um prazo' => ['30 dias', 1],
            'um prazo em maiúsculas' => ['10 DIAS', 1],
            'dois prazos' => ['30/60 dias', 2],
            'três prazos' => ['30/60/90 dias', 3],
            'três prazos com espaços' => ['30 / 60 / 90', 3],
            'espaços em volta' => ['  3x  ', 3],
            'valor em dinheiro' => ['488,02', null],
            'vazio' => ['', null],
            'nulo' => [null, null],
            'texto livre' => ['conforme contrato', null],
            'zero vezes' => ['0x', null],
            'vezes demais' => ['1000x', null],
        ];
    }
}
