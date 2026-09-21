<?php

namespace App\Support;

class Money
{
    public static function toCents(string $amount): int
    {
        $negative = str_starts_with($amount, '-');
        $absoluteAmount = $negative ? substr($amount, 1) : $amount;
        [$whole, $fraction] = array_pad(explode('.', $absoluteAmount, 2), 2, '');
        $fraction = str_pad(substr($fraction, 0, 2), 2, '0');
        $cents = ((int) $whole * 100) + (int) $fraction;

        return $negative ? -$cents : $cents;
    }

    public static function fromCents(int $cents): string
    {
        $sign = $cents < 0 ? '-' : '';
        $absoluteCents = abs($cents);

        return $sign.intdiv($absoluteCents, 100).'.'.str_pad((string) ($absoluteCents % 100), 2, '0', STR_PAD_LEFT);
    }

    public static function format(int $cents): string
    {
        $sign = $cents < 0 ? '-' : '';
        $absoluteCents = abs($cents);

        return $sign.number_format(intdiv($absoluteCents, 100)).'.'.str_pad((string) ($absoluteCents % 100), 2, '0', STR_PAD_LEFT);
    }
}
