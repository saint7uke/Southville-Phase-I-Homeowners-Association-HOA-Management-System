<?php

declare(strict_types=1);

namespace App\Support;

use InvalidArgumentException;

final class Money
{
    public static function toMinor(string|int $amount): int
    {
        $normalized = trim((string) $amount);

        if (! preg_match('/^(\d+)(?:\.(\d{1,2}))?$/', $normalized, $matches)) {
            throw new InvalidArgumentException('The amount must be a non-negative PHP amount with up to two decimal places.');
        }

        $major = (int) $matches[1];
        $minor = isset($matches[2]) ? (int) str_pad($matches[2], 2, '0') : 0;

        return ($major * 100) + $minor;
    }

    public static function fromMinor(int $amount): string
    {
        if ($amount < 0) {
            throw new InvalidArgumentException('A stored monetary amount cannot be negative.');
        }

        return intdiv($amount, 100).'.'.str_pad((string) ($amount % 100), 2, '0', STR_PAD_LEFT);
    }
}
