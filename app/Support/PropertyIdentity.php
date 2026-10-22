<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** A confirmed property does not depend on a resident's house number or street spelling. */
final class PropertyIdentity
{
    public static function suffix(mixed $value): ?string
    {
        if ($value !== null && ! is_string($value)) {
            return null;
        }
        $value = strtoupper(trim((string) $value));

        return $value === '' ? null : $value;
    }

    public static function phase(array|Model $address): string
    {
        $data = self::data($address);

        return filled($data['phase_suffix'] ?? null)
            ? 'Phase 1 - '.self::suffix($data['phase_suffix'])
            : ($data['phase'] ?? PropertyAddress::DEFAULT_PHASE);
    }

    public static function address(array|Model $address): string
    {
        $data = self::data($address);
        $parts = array_filter([
            filled($data['block'] ?? null) ? 'Block '.$data['block'] : null,
            filled($data['lot'] ?? null) ? 'Lot '.$data['lot'] : null,
            $data['house_number'] ?? null, $data['street'] ?? null,
        ], fn ($part): bool => filled($part));

        return implode(' ', $parts).', '.self::phase($data);
    }

    public static function baseKey(array|Model $address): string
    {
        $data = self::data($address);

        // Reuse the established block/lot prefix and Unicode normalization.
        return PropertyAddress::key([
            'phase' => $data['phase'] ?? PropertyAddress::DEFAULT_PHASE,
            'block' => $data['block'] ?? '', 'lot' => $data['lot'] ?? '',
        ]);
    }

    public static function confirmedKey(array|Model $address): ?string
    {
        $data = self::data($address);
        $suffix = self::suffix($data['phase_suffix'] ?? null);
        if ($suffix === null || ! preg_match('/^[A-Z0-9]{1,10}$/D', $suffix)
            || blank($data['block'] ?? null) || blank($data['lot'] ?? null)) {
            return null;
        }

        return hash('sha256', 'property-v2|'.self::baseKey($data).'|'.$suffix);
    }

    public static function key(array|Model $address): string
    {
        $data = self::data($address);

        return self::confirmedKey($data) ?? ($data['address_key'] ?? PropertyAddress::key($data));
    }

    public static function same(array|Model $left, array|Model $right): bool
    {
        return self::key($left) === self::key($right);
    }

    public static function scope(Builder $query, array|Model $address): Builder
    {
        $key = self::confirmedKey($address);

        return $key !== null
            ? $query->where('property_key', $key)
            : $query->whereNull('property_key')->where('address_key', self::key($address));
    }

    private static function data(array|Model $address): array
    {
        return $address instanceof Model ? $address->getAttributes() : $address;
    }
}
