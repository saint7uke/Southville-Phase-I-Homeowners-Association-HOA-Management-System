<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Homeowner;
use Illuminate\Database\Eloquent\Builder;

final class PropertyDuesAccounts
{
    public function primaryForAddress(string $addressKey): ?Homeowner
    {
        return $this->activeOwners()
            ->where('address_key', $addressKey)
            ->orderByRaw("CASE WHEN ownership_type = 'Owner' THEN 0 ELSE 1 END")
            ->orderBy('id')
            ->first();
    }

    public function payerIsEligible(Homeowner $payer, Homeowner $liable): bool
    {
        if ($payer->trashed() || ! in_array($payer->status, ['Active', 'Delinquent'], true)
            || $payer->user?->account_status !== 'Active' || $payer->user?->trashed()) {
            return false;
        }

        if ($payer->id === $liable->id) {
            return true;
        }

        if ($payer->address_key !== $liable->address_key) {
            return false;
        }

        return $payer->ownership_type === 'Co-owner'
            || ($payer->ownership_type === 'Tenant' && $payer->landlord_homeowner_id === $liable->id);
    }

    /** @return Builder<Homeowner> */
    public function eligiblePayers(Homeowner $liable): Builder
    {
        return Homeowner::query()
            ->whereIn('status', ['Active', 'Delinquent'])
            ->where('address_key', $liable->address_key)
            ->whereHas('user', fn (Builder $query) => $query->where('account_status', 'Active')->whereNull('users.deleted_at'))
            ->where(function (Builder $query) use ($liable): void {
                $query->whereKey($liable->id)
                    ->orWhere('ownership_type', 'Co-owner')
                    ->orWhere(fn (Builder $query) => $query
                        ->where('ownership_type', 'Tenant')
                        ->where('landlord_homeowner_id', $liable->id));
            });
    }

    /** @return Builder<Homeowner> */
    public function activeOwners(): Builder
    {
        return Homeowner::query()
            ->whereIn('status', ['Active', 'Delinquent'])
            ->whereIn('ownership_type', ['Owner', 'Co-owner'])
            ->whereNotNull('address_key')
            ->whereHas('user', fn (Builder $query) => $query->where('account_status', 'Active')->whereNull('users.deleted_at'));
    }
}
