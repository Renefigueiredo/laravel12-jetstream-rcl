<?php

namespace Tests\Unit\Conciliation;

use App\Services\Import\HeaderNormalizer;
use PHPUnit\Framework\TestCase;

class HeaderNormalizerTest extends TestCase
{
    public function test_it_ignores_case_accents_and_surrounding_spaces(): void
    {
        $normalizer = new HeaderNormalizer;

        $this->assertSame('MAP_SOLICITACAO', $normalizer->normalize('  map_solicitação '));
        $this->assertSame('VALOR', $normalizer->normalize('Valor'));
        $this->assertSame('DT_LIQUIDACAO', $normalizer->normalize("\u{FEFF}DT_LIQUIDACAO"));
    }

    public function test_any_other_difference_is_a_different_name(): void
    {
        $normalizer = new HeaderNormalizer;

        $this->assertNotSame($normalizer->normalize('VL_RECEBIDO'), $normalizer->normalize('VL RECEBIDO'));
        $this->assertNotSame($normalizer->normalize('VALOR'), $normalizer->normalize('VALORES'));
    }

    public function test_non_text_headers_become_empty(): void
    {
        $this->assertSame('', (new HeaderNormalizer)->normalize(null));
        $this->assertSame('2026', (new HeaderNormalizer)->normalize(2026));
    }
}
