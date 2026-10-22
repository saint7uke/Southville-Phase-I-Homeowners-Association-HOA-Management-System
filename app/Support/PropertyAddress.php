<?php

declare(strict_types=1);

namespace App\Support;

final class PropertyAddress
{
    public const DEFAULT_PHASE = 'Southville Phase I';

    /** @param array<string, mixed> $address @return array{house_number: string, street: string, block: string, lot: string, phase: string} */
    public static function normalizeForStorage(array $address): array
    {
        return [
            'house_number' => self::collapse($address['house_number'] ?? ''),
            'street' => self::collapse($address['street'] ?? ''),
            'block' => mb_strtoupper(self::collapse($address['block'] ?? ''), 'UTF-8'),
            'lot' => mb_strtoupper(self::collapse($address['lot'] ?? ''), 'UTF-8'),
            'phase' => self::collapse($address['phase'] ?? self::DEFAULT_PHASE) ?: self::DEFAULT_PHASE,
            ...(array_key_exists('phase_suffix', $address) ? ['phase_suffix' => PropertyIdentity::suffix($address['phase_suffix'])] : []),
        ];
    }

    /** @param array<string, mixed> $address */
    public static function key(array $address): string
    {
        $address = self::normalizeForStorage($address);

        $parts = [
            self::identifier($address['phase']),
            self::identifier($address['block'], ['BLOCK', 'BLK', 'B']),
            self::identifier($address['lot'], ['LOT', 'L']),
            self::identifier($address['house_number'], ['HOUSE', 'UNIT', 'NUMBER', 'NO']),
            self::street($address['street']),
        ];

        return hash('sha256', implode('|', $parts));
    }

    private static function collapse(mixed $value): string
    {
        if ($value !== null && ! is_scalar($value)) {
            return '';
        }
        $value = (string) $value;
        if (class_exists(\Normalizer::class)) {
            $value = \Normalizer::normalize($value, \Normalizer::FORM_KC) ?: $value;
        }

        return trim((string) preg_replace('/\s+/u', ' ', $value));
    }

    /** @param list<string> $prefixes */
    private static function identifier(string $value, array $prefixes = []): string
    {
        $value = mb_strtoupper($value, 'UTF-8');
        $value = (string) preg_replace('/[^\pL\pN]+/u', ' ', $value);
        $value = trim((string) preg_replace('/\s+/u', ' ', $value));

        if ($prefixes !== []) {
            $pattern = '/^(?:'.implode('|', array_map(fn (string $prefix): string => preg_quote($prefix, '/'), $prefixes)).')(?:\s+|$)/u';
            $value = trim((string) preg_replace($pattern, '', $value, 1));
        }

        return $value;
    }

    private static function street(string $value): string
    {
        $value = self::identifier($value);
        $suffixes = [
            'ST' => 'STREET',
            'RD' => 'ROAD',
            'AVE' => 'AVENUE',
            'AV' => 'AVENUE',
            'DR' => 'DRIVE',
            'LN' => 'LANE',
            'CT' => 'COURT',
            'BLVD' => 'BOULEVARD',
            'HWY' => 'HIGHWAY',
        ];

        return (string) preg_replace_callback(
            '/\b('.implode('|', array_keys($suffixes)).')$/u',
            static fn (array $matches): string => $suffixes[$matches[1]],
            $value,
        );
    }
}
