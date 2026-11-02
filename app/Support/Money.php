<?php

namespace App\Support;

use InvalidArgumentException;

class Money
{
    public static function toCents(string|int $amount): int
    {
        $value = (string) $amount;

        if (! preg_match('/^(\d+)(?:\.(\d{1,2}))?$/', $value, $matches)) {
            throw new InvalidArgumentException('Money values must be non-negative decimals with at most two places.');
        }

        return ((int) $matches[1] * 100) + (int) str_pad($matches[2] ?? '', 2, '0');
    }

    public static function fromCents(int $cents): string
    {
        if ($cents < 0) {
            throw new InvalidArgumentException('Money values cannot be negative.');
        }

        return intdiv($cents, 100).'.'.str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
    }

    public static function percentage(int $amountCents, string $percentage): int
    {
        if (! preg_match('/^(\d+)(?:\.(\d{1,4}))?$/', $percentage, $matches)) {
            throw new InvalidArgumentException('Percentage values must be decimals with at most four places.');
        }

        $ticks = ((int) $matches[1] * 10_000) + (int) str_pad($matches[2] ?? '', 4, '0');

        return intdiv(($amountCents * $ticks) + 500_000, 1_000_000);
    }
}
