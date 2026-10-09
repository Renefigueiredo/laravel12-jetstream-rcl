<?php

namespace App\Services\Reconciliation\Matching;

final class PaymentConditionParser
{
    /**
     * More instalments than this is taken as a text the engine does not understand.
     */
    private const MAX_INSTALLMENTS = 120;

    /**
     * How many instalments the payment condition of an authorization foresees.
     *
     * The condition is only a hint: a text that is not recognised gives null, never an error.
     */
    public function installments(?string $condition): ?int
    {
        $text = strtoupper(trim((string) iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', (string) $condition)));
        $text = (string) preg_replace('/\s+/', ' ', $text);

        if (preg_match('/^A VISTA$/', $text) === 1) {
            return 1;
        }

        if (preg_match('/^(\d{1,4}) ?X$/', $text, $found) === 1) {
            $installments = (int) $found[1];

            return $installments >= 1 && $installments <= self::MAX_INSTALLMENTS ? $installments : null;
        }

        if (preg_match('/^\d{1,3}( ?\/ ?\d{1,3})*( ?(DIAS|DIA|DDL))?$/', $text) === 1) {
            return substr_count($text, '/') + 1;
        }

        return null;
    }
}
