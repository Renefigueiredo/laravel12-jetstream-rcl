<?php

namespace App\Services\Reconciliation\Matching;

use Illuminate\Support\Str;

final class CardNumberExtractor
{
    /**
     * Read the card of a payment: the first four digits that follow the marker in the species.
     */
    public static function extract(?string $species, string $marker): ?string
    {
        if ($species === null || $marker === '') {
            return null;
        }

        $text = self::normalize($species);
        $position = strpos($text, self::normalize($marker));

        if ($position === false) {
            return null;
        }

        $rest = substr($text, $position + strlen(self::normalize($marker)));

        return preg_match('/^\s*(\d{4})/', $rest, $matches) === 1 ? $matches[1] : null;
    }

    private static function normalize(string $text): string
    {
        return trim((string) preg_replace('/\s+/', ' ', strtoupper(Str::ascii($text))));
    }
}
