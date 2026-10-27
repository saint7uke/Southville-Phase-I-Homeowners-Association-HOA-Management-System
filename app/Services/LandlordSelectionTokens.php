<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Homeowner;
use App\Support\PropertyAddress;
use App\Support\PropertyIdentity;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Validation\ValidationException;
use Throwable;

final class LandlordSelectionTokens
{
    private const TTL_MINUTES = 10;

    /** @param array<string, mixed> $address */
    public function issue(Homeowner $homeowner, array $address): string
    {
        $nonce = bin2hex(random_bytes(24));
        Cache::put($this->cacheKey($nonce), true, now()->addMinutes(self::TTL_MINUTES));

        return Crypt::encryptString(json_encode([
            'homeowner_id' => $homeowner->getKey(),
            'property_key' => PropertyIdentity::key($address),
            'street_key' => PropertyAddress::streetKey($address['street'] ?? null),
            'nonce' => $nonce,
            'expires_at' => now()->addMinutes(self::TTL_MINUTES)->getTimestamp(),
        ], JSON_THROW_ON_ERROR));
    }

    /** @param array<string, mixed> $address */
    public function consume(string $token, array $address): Homeowner
    {
        try {
            $payload = json_decode(Crypt::decryptString($token), true, flags: JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            throw $this->invalid();
        }

        if (! is_array($payload)
            || ! is_int($payload['homeowner_id'] ?? null)
            || ! is_string($payload['property_key'] ?? null)
            || ! is_string($payload['street_key'] ?? null)
            || ! is_string($payload['nonce'] ?? null)
            || ! is_int($payload['expires_at'] ?? null)
            || $payload['expires_at'] < now()->getTimestamp()
            || ! hash_equals(PropertyIdentity::key($address), $payload['property_key'])
            || ! hash_equals(PropertyAddress::streetKey($address['street'] ?? null), $payload['street_key'])
            || Cache::pull($this->cacheKey($payload['nonce'])) !== true) {
            throw $this->invalid();
        }

        $homeowner = Homeowner::query()
            ->whereKey($payload['homeowner_id'])
            ->whereIn('ownership_type', ['Owner', 'Co-owner'])
            ->where('status', 'Active')
            ->whereHas('user', fn ($query) => $query->where('account_status', 'Active')->whereNull('users.deleted_at'))
            ->with('user')
            ->lockForUpdate()
            ->first();

        if (! $homeowner instanceof Homeowner || ! PropertyIdentity::same($homeowner, $address)
            || ! PropertyAddress::sameStreet($homeowner->street, $address['street'] ?? null)) {
            throw $this->invalid();
        }

        return $homeowner;
    }

    private function cacheKey(string $nonce): string
    {
        return 'landlord-selection:'.hash('sha256', $nonce);
    }

    private function invalid(): ValidationException
    {
        return ValidationException::withMessages([
            'landlord_name' => __('Select a registered landlord for the entered property.'),
        ]);
    }
}
