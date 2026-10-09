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
}
