<?php

declare(strict_types=1);

namespace App\Support;

final class PersonName
{
    public static function normalize(mixed $value): ?string
    {
        $collapsed = preg_replace('/\s+/u', ' ', trim((string) $value));

        if (blank($collapsed)) {
            return null;
        }

        $titleCased = mb_convert_case($collapsed, MB_CASE_TITLE, 'UTF-8');

        return preg_replace_callback(
            "/(['-])(\pL)/u",
            static fn (array $matches): string => $matches[1].mb_strtoupper($matches[2], 'UTF-8'),
            $titleCased,
        );
    }

    public static function key(mixed $value): string
    {
        $value = (string) $value;
        if (class_exists(\Normalizer::class)) {
            $value = \Normalizer::normalize($value, \Normalizer::FORM_KC) ?: $value;
        }

        $value = mb_strtoupper($value, 'UTF-8');
        $value = (string) preg_replace('/[^\pL\pN]+/u', ' ', $value);

        return trim((string) preg_replace('/\s+/u', ' ', $value));
    }
}
