<?php

namespace App\Services\ExcludedCodes;

final class OperationCode
{
    public const MAX_LENGTH = 20;

    public const DESCRIPTION_MAX_LENGTH = 255;

    /**
     * Remove the spaces around the code; null when nothing is left.
     */
    public static function normalize(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $code = trim(str_replace("\u{00A0}", ' ', $value));

        return $code === '' ? null : $code;
    }

    /**
     * A code has from 1 to 20 unaccented letters or digits.
     */
    public static function isValid(string $code): bool
    {
        return preg_match('/\A[A-Za-z0-9]{1,'.self::MAX_LENGTH.'}\z/', $code) === 1;
    }
}
