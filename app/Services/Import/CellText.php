<?php

namespace App\Services\Import;

use DateTimeInterface;

class CellText
{
    /**
     * Represent a cell as trimmed text, or null when it is empty.
     */
    public static function from(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof DateTimeInterface) {
            return $value->format('d/m/Y');
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (is_float($value)) {
            $value = floor($value) === $value && abs($value) < 1e15
                ? number_format($value, 0, '', '')
                : rtrim(rtrim(number_format($value, 6, '.', ''), '0'), '.');
        }

        if (! is_scalar($value)) {
            return null;
        }

        $text = trim(str_replace("\u{00A0}", ' ', (string) $value));

        return $text === '' ? null : $text;
    }
}
