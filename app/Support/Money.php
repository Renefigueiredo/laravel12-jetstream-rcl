<?php

namespace App\Support;

final class Money
{
    /**
     * The single place where integer cents become text for the screen: R$ 1.250,40.
     */
    public static function format(?int $cents): string
    {
        if ($cents === null) {
            return '';
        }

        return ($cents < 0 ? '-' : '').'R$ '.number_format(abs($cents) / 100, 2, ',', '.');
    }

    /**
     * Read an amount typed by a person (288,70; 1.234,56; R$ 288; 288.70) as integer cents.
     * Anything that is not an amount gives null, so a search or a filter simply ignores it.
     */
    public static function parse(?string $text): ?int
    {
        $text = trim(str_replace(['R$', ' '], '', (string) $text));

        if (preg_match('/\A\d{1,3}(\.\d{3})+(,\d{1,2})?\z|\A\d+(,\d{1,2})?\z/', $text) === 1) {
            [$whole, $fraction] = array_pad(explode(',', str_replace('.', '', $text), 2), 2, '');
        } elseif (preg_match('/\A\d+\.\d{1,2}\z/', $text) === 1) {
            [$whole, $fraction] = explode('.', $text, 2);
        } else {
            return null;
        }

        return (int) $whole * 100 + (int) str_pad($fraction, 2, '0');
    }
}
