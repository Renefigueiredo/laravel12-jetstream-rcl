<?php

namespace App\Services\Import;

use Illuminate\Support\Str;

class HeaderNormalizer
{
    /**
     * Normalize a header so that case, accents and surrounding spaces do not matter.
     */
    public function normalize(mixed $header): string
    {
        if (! is_scalar($header)) {
            return '';
        }

        $text = str_replace("\u{FEFF}", '', (string) $header);

        return Str::upper(Str::ascii(trim($text)));
    }
}
