<?php

declare(strict_types=1);

namespace App\Support;

final class CertificateTypeName
{
    public static function normalize(mixed $value): string
    {
        $value = (string) $value;
        if (class_exists(\Normalizer::class)) {
            $value = \Normalizer::normalize($value, \Normalizer::FORM_KC) ?: $value;
        }

        return trim((string) preg_replace('/\s+/u', ' ', $value));
    }

    public static function key(mixed $value): string
    {
        return hash('sha256', mb_strtoupper(self::normalize($value), 'UTF-8'));
    }
}
