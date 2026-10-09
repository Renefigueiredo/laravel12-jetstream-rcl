<?php

namespace App\Services\Reconciliation\Matching;

use Illuminate\Support\Str;

final class SupplierNameNormalizer
{
    /**
     * Corporate suffixes that say nothing about who the supplier is, with their usual misspellings.
     *
     * @var list<string>
     */
    private const SUFFIXES = [
        'LTDA', 'LDTA', 'LTD', 'LIMITADA',
        'EIRELI', 'EIRELE', 'EIRELES', 'EIRELLI', 'EIRELIS',
        'ME', 'MEI', 'EPP', 'SA', 'CIA', 'SS',
    ];

    /**
     * Reduce a supplier name to the words that identify it: upper case, no accents,
     * no punctuation, no document numbers and no corporate suffixes. Initials written apart
     * ("M F MATERIAIS") are joined ("MF MATERIAIS").
     */
    public function normalize(?string $name): string
    {
        $text = strtoupper(Str::ascii(str_replace(["'", '’', '`', '´'], '', (string) $name)));
        $text = (string) preg_replace('/\bS\s*[\/.]\s*A\b\.?/', ' SA ', $text);
        $text = (string) preg_replace('/[^A-Z0-9]+/', ' ', $text);
        $text = (string) preg_replace('/\b([A-Z]) (?=[A-Z]\b)/', '$1', $text);

        $words = array_filter(
            explode(' ', $text),
            fn (string $word): bool => $word !== '' && ! ctype_digit($word) && ! in_array($word, self::SUFFIXES, true),
        );

        return implode(' ', $words);
    }
}
