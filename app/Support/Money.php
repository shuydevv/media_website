<?php

namespace App\Support;

class Money
{
    public static function format(int $cents, string $currency = 'RUB'): string
    {
        $symbol = $currency === 'RUB' ? '₽' : $currency;

        return number_format($cents / 100, 2, ',', ' ') . ' ' . $symbol;
    }
}
