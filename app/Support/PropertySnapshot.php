<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Homeowner;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

final class PropertySnapshot
{
    private const FIELDS = ['house_number', 'street', 'block', 'lot', 'phase', 'phase_suffix'];

    /** @return array<string, mixed> */
    public static function from(array|Model $source): array
    {
        $attributes = $source instanceof Model ? $source->getAttributes() : $source;
        $address = PropertyAddress::normalizeForStorage(Arr::only($attributes, self::FIELDS));
        $normalized = [...$attributes, ...$address];

        return [
            ...$address,
            'property_key' => PropertyIdentity::confirmedKey($normalized) ?? PropertyIdentity::key($normalized),
            'display_address' => PropertyIdentity::address($normalized),
        ];
    }

    public static function display(?array $snapshot, ?Homeowner $fallback = null): string
    {
        if (filled($snapshot['display_address'] ?? null)) {
            return (string) $snapshot['display_address'];
        }

        if ($snapshot !== null && Arr::hasAny($snapshot, self::FIELDS)) {
            return PropertyIdentity::address($snapshot);
        }

        return $fallback?->full_address ?? 'Archived property';
    }
}
