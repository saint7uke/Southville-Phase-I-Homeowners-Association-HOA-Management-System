<?php

declare(strict_types=1);

namespace App\Support;

use Normalizer;

final class PaymentReference
{
    public static function display(?string $value): ?string
    {
        $value = trim((string) $value);
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

        return $value === '' ? null : $value;
    }

    public static function key(?string $value): ?string
    {
        $value = self::display($value);
        if ($value === null) {
            return null;
        }
        if (class_exists(Normalizer::class)) {
            $value = Normalizer::normalize($value, Normalizer::FORM_KC) ?: $value;
        }

        return mb_strtolower(preg_replace('/[^\pL\pN]+/u', '', $value) ?? $value, 'UTF-8');
    }
}
