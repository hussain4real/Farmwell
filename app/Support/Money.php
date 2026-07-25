<?php

namespace App\Support;

use InvalidArgumentException;

class Money
{
    public static function toMinorUnit(string|int|float $amount): int
    {
        $value = trim(str_replace(',', '', (string) $amount));

        if (! preg_match('/^\d+(\.\d{1,2})?$/', $value)) {
            throw new InvalidArgumentException('Money amounts must be positive decimals with up to two places.');
        }

        [$major, $minor] = array_pad(explode('.', $value, 2), 2, '0');

        return ((int) $major * 100) + (int) str_pad($minor, 2, '0');
    }

    public static function toDecimal(int $minorUnits): string
    {
        $sign = $minorUnits < 0 ? '-' : '';
        $absolute = abs($minorUnits);
        $major = intdiv($absolute, 100);
        $minor = $absolute % 100;

        return sprintf('%s%d.%02d', $sign, $major, $minor);
    }

    public static function normalizeCurrency(?string $currency, string $fallback = 'NGN'): string
    {
        $normalized = strtoupper(trim((string) ($currency ?: $fallback)));

        if (! preg_match('/^[A-Z]{3}$/', $normalized)) {
            throw new InvalidArgumentException('Currency must be a three-letter ISO code.');
        }

        return $normalized;
    }
}
