<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Homeowner;
use App\Support\PropertyAddress;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class PropertyAddressAvailability
{
    public const AVAILABLE_MESSAGE = 'This address is available.';

    public const TENANT_ALLOWED_MESSAGE = 'This address can be used for a Tenant application.';

    public const CO_OWNER_ALLOWED_MESSAGE = 'This address can be used for a Co-owner application.';

    public const UNAVAILABLE_MESSAGE = 'This address is already registered. Please use a different address.';

    /** @param array<string, mixed> $data */
    public function message(array $data, bool $available): string
    {
        if (! $available) {
            return self::UNAVAILABLE_MESSAGE;
        }

        return match ($data['ownership_type'] ?? null) {
            'Tenant' => self::TENANT_ALLOWED_MESSAGE,
            'Co-owner' => self::CO_OWNER_ALLOWED_MESSAGE,
            default => self::AVAILABLE_MESSAGE,
        };
    }

    /** @param array<string, mixed> $data */
    public function available(array $data, ?int $ignoreHomeownerId = null): bool
    {
        if (($data['ownership_type'] ?? null) !== 'Owner') {
            return true;
        }

        return ! Homeowner::query()
            ->where('address_key', PropertyAddress::key($data))
            ->when($ignoreHomeownerId !== null, fn ($query) => $query->whereKeyNot($ignoreHomeownerId))
            ->exists();
    }

    /** @param array<string, mixed> $data */
    public function assertAvailable(array $data, ?int $ignoreHomeownerId = null): void
    {
        if (! $this->available($data, $ignoreHomeownerId)) {
            throw ValidationException::withMessages([
                'address' => __(self::UNAVAILABLE_MESSAGE),
            ]);
        }
    }

    /** @template T @param array<string, mixed> $data @param Closure(): T $callback @return T */
    public function withRegistrationLock(array $data, Closure $callback): mixed
    {
        return $this->withAddressLock($data, null, $callback);
    }

    /** @template T @param array<string, mixed> $data @param Closure(): T $callback @return T */
    public function withAddressLock(array $data, ?int $ignoreHomeownerId, Closure $callback, bool $assertAvailable = true): mixed
    {
        return DB::transaction(function () use ($data, $ignoreHomeownerId, $callback, $assertAvailable): mixed {
            $key = PropertyAddress::key($data);
            $now = now();

            DB::table('property_address_registry')->insertOrIgnore([
                'address_key' => $key,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('property_address_registry')
                ->where('address_key', $key)
                ->lockForUpdate()
                ->first();

            if ($assertAvailable) {
                $this->assertAvailable($data, $ignoreHomeownerId);
            }

            return $callback();
        }, 3);
    }
}
