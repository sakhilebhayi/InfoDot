<?php

namespace App\Support;

/** Shows an amount held in minor units (cents) as a person reads it. It formats; it never supplies an amount or a currency. */
final class Money
{
    /** Currencies whose minor unit is the unit itself. */
    private const NO_DECIMALS = ['BIF', 'CLP', 'DJF', 'GNF', 'JPY', 'KMF', 'KRW', 'PYG', 'RWF', 'UGX', 'VND', 'VUV', 'XAF', 'XOF', 'XPF'];

    public static function format(?int $minor, ?string $currency): string
    {
        if ($minor === null || $currency === null) {
            return 'No price set';
        }

        $whole = in_array($currency, self::NO_DECIMALS, true);

        return $currency.' '.number_format($whole ? $minor : $minor / 100, $whole ? 0 : 2, '.', ' ');
    }
}
