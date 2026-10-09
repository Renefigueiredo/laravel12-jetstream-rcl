<?php

namespace Tests\Unit\Reconciliation;

use App\Services\Reconciliation\Matching\SupplierNameNormalizer;
use App\Services\Reconciliation\Matching\SupplierSimilarity;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class SupplierSimilarityTest extends TestCase
{
    protected function between(string $first, string $second): int
    {
        $normalizer = new SupplierNameNormalizer;

        return (new SupplierSimilarity)->between($normalizer->normalize($first), $normalizer->normalize($second));
    }

    #[DataProvider('pairs')]
    public function test_compatibility_falls_in_the_expected_range(string $authorization, string $payment, int $lowest, int $highest): void
    {
        $score = $this->between($authorization, $payment);

        $this->assertGreaterThanOrEqual($lowest, $score);
        $this->assertLessThanOrEqual($highest, $score);
        $this->assertSame($score, $this->between($payment, $authorization), 'The measure must be the same in both directions.');
    }

    /**
     * @return array<string, array{string, string, int, int}>
     */
    public static function pairs(): array
    {
        return [
            'only the suffix differs' => ['PADARIA PERNAMBUCANA LTDA', 'PADARIA PERNAMBUCANA', 100, 100],
            'longer name contains the shorter' => ['PADARIA PERNAMBUCANA', 'PADARIA PERNAMBUCANA COMERCIO DE ALIMENTOS', 100, 100],
            'document number in front' => ['LILIAN KARLA GUILHERME SANTOS', '60.499.628 LILIAN KARLA GUILHERME SANTOS', 100, 100],
            'typing mistake' => ['PAPELARIA CENTRAL', 'PAPELARIA CENTRAU', 90, 99],
            'two swapped letters' => ['LAVANDEIRA TAKI LTDA', 'LAVANDERIA TAKI LTDA', 90, 99],
            'swapped letters at the start' => ['PAPELARIA CENTRAL', 'APPELARIA CENTRAL', 90, 99],
            'one word in the singular' => ['FN COMERCIO DE ALIMENTOS LTDA', 'FN COMERCIO DE ALIMENTO EIRELES ME', 90, 99],
            'mistyped word and an extra one' => ['FN COMERCIO DE ALIMENTOS', 'FN COMERCIO DE ALIMENTO NORDESTE', 90, 99],
            'short distinguishing word differs' => ['FN COMERCIO DE ALIMENTOS', 'JR COMERCIO DE ALIMENTOS', 0, 89],
            'different companies of the same trade' => ['ALFA COMERCIO DE ALIMENTOS', 'BETA COMERCIO DE ALIMENTOS', 0, 89],
            'short word differs' => ['CASA BOA VISTA', 'CASA BOM VISTA', 60, 89],
            'apostrophe' => ["LIBA'S GRILL RESTAURANTE E PIZZARIA", 'LIBAS GRILL RESTAURANTE E PIZZARIA', 100, 100],
            'two separate mistakes' => ['PAPELARIA CENTRAL', 'PAPELERIA CENTRAU', 80, 89],
            'one shared word' => ['PAPELARIA CENTRAL', 'PAPELARIA DO BAIRRO', 0, 89],
            'nothing in common' => ['PAPELARIA CENTRAL', 'POSTO DE COMBUSTIVEL ALFA', 0, 59],
            'one-word name inside a longer one' => ['SILVA', 'JOSE DA SILVA TRANSPORTES', 0, 59],
            'same one-word name' => ['SILVA', 'SILVA', 100, 100],
            'connectives do not count as shared words' => ['CASA DE CARNES', 'POSTO DE GASOLINA', 0, 59],
        ];
    }

    public function test_an_empty_name_is_compatible_with_nothing(): void
    {
        $this->assertSame(0, $this->between('', 'PADARIA PERNAMBUCANA'));
        $this->assertSame(0, $this->between('LTDA', 'LTDA'));
    }
}
