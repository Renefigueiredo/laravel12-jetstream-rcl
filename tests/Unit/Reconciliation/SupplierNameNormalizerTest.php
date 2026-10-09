<?php

namespace Tests\Unit\Reconciliation;

use App\Services\Reconciliation\Matching\SupplierNameNormalizer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class SupplierNameNormalizerTest extends TestCase
{
    #[DataProvider('names')]
    public function test_names_are_reduced_to_the_identifying_words(?string $name, string $expected): void
    {
        $this->assertSame($expected, (new SupplierNameNormalizer)->normalize($name));
    }

    /**
     * @return array<string, array{string|null, string}>
     */
    public static function names(): array
    {
        return [
            'suffix and punctuation' => ['Padaria Pernambucana Ltda.', 'PADARIA PERNAMBUCANA'],
            'document number in front' => ['60.499.628 LILIAN KARLA GUILHERME SANTOS', 'LILIAN KARLA GUILHERME SANTOS'],
            'cnpj, accents and S/A' => ['12.345.678/0001-90 - COMÉRCIO SÃO JOSÉ S/A', 'COMERCIO SAO JOSE'],
            'S.A. with dots' => ['Banco Exemplo S.A.', 'BANCO EXEMPLO'],
            'ampersand and two suffixes' => ['J. SILVA   &   CIA  ME', 'J SILVA'],
            'suffix on both ends' => ['EIRELI MATERIAIS EIRELI', 'MATERIAIS'],
            'letters and digits in one word stay' => ['3M do Brasil Ltda', '3M DO BRASIL'],
            'suffix inside a word stays' => ['MERCADO LIVRE BRASIL EBAZAR', 'MERCADO LIVRE BRASIL EBAZAR'],
            'apostrophe joins the word' => ["LIBA'S GRILL RESTAURANTE", 'LIBAS GRILL RESTAURANTE'],
            'initials written apart are joined' => ['M F Materiais de Construção', 'MF MATERIAIS DE CONSTRUCAO'],
            'three initials' => ['A. B. C. Transportes', 'ABC TRANSPORTES'],
            'S A written apart is still a suffix' => ['Banco Exemplo S A', 'BANCO EXEMPLO'],
            'misspelt suffixes' => ['FN COMERCIO DE ALIMENTO EIRELES ME', 'FN COMERCIO DE ALIMENTO'],
            'suffix written in full' => ['Papelaria Central Limitada', 'PAPELARIA CENTRAL'],
            'only suffixes' => ['LTDA ME', ''],
            'empty' => ['', ''],
            'null' => [null, ''],
        ];
    }
}
