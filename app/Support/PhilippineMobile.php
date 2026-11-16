<?php

declare(strict_types=1);

namespace App\Support;

final class PhilippineMobile
{
    public const INPUT_REGEX = '/\A09[0-9]{9}\z/';

    public static function inputRules(): array
    {
        return ['bail', 'required', 'string', 'regex:'.self::INPUT_REGEX];
    }

    public static function normalize(mixed $number): ?string
    {
        if (! is_string($number)) {
            return null;
        }
        $number = preg_replace('/[\s().-]+/u', '', trim($number));
        if (preg_match('/^09\d{9}$/D', $number)) {
            return '+63'.substr($number, 1);
        }
        if (preg_match('/^\+?639\d{9}$/D', $number)) {
            return '+'.ltrim($number, '+');
        }

        return null;
    }

    public static function fingerprint(string $number): string
    {
        return hash_hmac('sha256', $number, (string) config('app.key'));
    }

    public static function local(mixed $number): mixed
    {
        $normalized = self::normalize($number);

        return $normalized ? '0'.substr($normalized, 3) : $number;
    }

    public static function mask(?string $number): string
    {
        return $number ? '+63 *** *** '.substr($number, -4) : '—';
    }
}
