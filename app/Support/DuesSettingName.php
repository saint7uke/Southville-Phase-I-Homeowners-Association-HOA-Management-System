<?php

declare(strict_types=1);

namespace App\Support;

use Normalizer;

final class DuesSettingName
{
    public static function display(?string $value): string
    {
        $value = trim((string) $value);
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

        return $value;
    }

    public static function key(?string $value): string
    {
        $value = self::display($value);
        if (class_exists(Normalizer::class)) {
            $value = Normalizer::normalize($value, Normalizer::FORM_KC) ?: $value;
        }

        return mb_strtolower($value, 'UTF-8');
    }
}
