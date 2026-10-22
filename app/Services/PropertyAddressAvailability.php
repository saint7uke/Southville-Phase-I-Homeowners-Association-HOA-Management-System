<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Homeowner;
use App\Support\PropertyIdentity;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class PropertyAddressAvailability
{
    public const AVAILABLE_MESSAGE = 'This address is available.';

    public const TENANT_ALLOWED_MESSAGE = 'This address can be used for a Tenant application.';

    public const CO_OWNER_ALLOWED_MESSAGE = 'This address can be used for a Co-owner application.';

    public const UNAVAILABLE_MESSAGE = 'This address is already registered. Please use a different address.';

    public const REVIEW_MESSAGE = 'This property has an unconfirmed phase. Please contact the HOA for review before registering or linking a landlord.';

    public const BILLED_MOVE_MESSAGE = 'This account has current-period property dues. Ask the HOA to review the assessment before moving the liable account to another property.';

    public function financialMoveNeedsReview(Homeowner $resident, array $address): bool
    {
        $original = $resident->getRawOriginal();
        $movesProperty = PropertyIdentity::baseKey($original) !== PropertyIdentity::baseKey($address)
            || (filled($original['property_key'] ?? null) && PropertyIdentity::key($original) !== PropertyIdentity::key($address));

        return $movesProperty && $resident->duesObligations()->where('billing_year', now()->year)
            ->where('billing_month', now()->month)->where('status', '!=', 'Cancelled')->lockForUpdate()->first(['id']) !== null;
    }

    public function needsReview(array $data, ?int $ignoreHomeownerId = null): bool
    {
        if (PropertyIdentity::confirmedKey($data) === null) {
            return false;
        }

        // Legacy block/lot spellings are normalized in PHP; no SQL collation assumptions.
        return Homeowner::query()->whereNull('property_key')
            ->when($ignoreHomeownerId !== null, fn ($query) => $query->whereKeyNot($ignoreHomeownerId))
            ->whereHas('user', fn ($query) => $query->whereNull('users.deleted_at'))
            ->get(['id', 'phase', 'block', 'lot'])
            ->contains(fn (Homeowner $resident): bool => PropertyIdentity::baseKey($resident) === PropertyIdentity::baseKey($data));
    }

    /** @param array<string, mixed> $data */
    public function message(array $data, bool $available): string
    {
        if (! $available) {
            return $this->needsReview($data) ? self::REVIEW_MESSAGE : self::UNAVAILABLE_MESSAGE;
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
        if ($this->needsReview($data, $ignoreHomeownerId)) {
            return false;
        }
        if (($data['ownership_type'] ?? null) !== 'Owner') {
            return true;
        }

        return ! PropertyIdentity::scope(Homeowner::query(), $data)
            ->whereHas('user', fn ($query) => $query->whereNull('users.deleted_at'))
            ->when($ignoreHomeownerId !== null, fn ($query) => $query->whereKeyNot($ignoreHomeownerId))
            ->exists();
    }

    /** @param array<string, mixed> $data */
    public function assertAvailable(array $data, ?int $ignoreHomeownerId = null): void
    {
        if (! $this->available($data, $ignoreHomeownerId)) {
            throw ValidationException::withMessages([
                'address' => __($this->needsReview($data, $ignoreHomeownerId) ? self::REVIEW_MESSAGE : self::UNAVAILABLE_MESSAGE),
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
            $this->lockProperty($data);

            if ($assertAvailable) {
                $this->assertAvailable($data, $ignoreHomeownerId);
            }

            return $callback();
        }, 3);
    }

    /** Must be called inside the transaction that reads/writes the property. */
    public function lockProperty(array $data): void
    {
        if (DB::transactionLevel() === 0) {
            throw new \LogicException('A property lock requires a transaction.');
        }
        // Legacy and confirmed identities share the base mutex; always take locks in order.
        $keys = array_unique([PropertyIdentity::baseKey($data), PropertyIdentity::key($data)]);
        sort($keys);
        foreach ($keys as $key) {
            DB::table('property_address_registry')->insertOrIgnore([
                'address_key' => $key, 'created_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('property_address_registry')->where('address_key', $key)->lockForUpdate()->first();
        }
    }
}
