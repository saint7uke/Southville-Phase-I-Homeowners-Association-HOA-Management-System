<?php

declare(strict_types=1);

namespace App\Services;

use App\Actions\Households\ManageHouseholdHead;
use App\Models\Homeowner;
use App\Models\Household;
use App\Models\HouseholdMember;
use App\Support\PropertyAddress;
use App\Support\PropertyIdentity;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class HouseholdDirectory
{
    private ?bool $ready = null;

    public function reviewReason(Homeowner $resident): ?string
    {
        if (! $resident->user || $resident->trashed() || $resident->user->trashed()) {
            return 'Archived or missing account';
        }
        if (in_array($resident->user->account_status, ['Pending', 'Rejected'], true)) {
            return 'Application not approved';
        }
        foreach (['block', 'lot', 'phase'] as $field) {
            if (blank($resident->{$field})) {
                return 'Incomplete address';
            }
        }
        if ($resident->address_key !== PropertyAddress::key($resident->getAttributes())) {
            return 'Inconsistent normalized address';
        }
        if (PropertyIdentity::scope(Homeowner::query(), $resident)->where('ownership_type', 'Owner')->count() > 1) {
            return 'Multiple Owner records require review';
        }

        return null;
    }

    public function synchronize(Homeowner $resident): void
    {
        $this->ready ??= Schema::hasTable('household_members');
        if (! $this->ready) {
            return;
        }
        DB::transaction(function () use ($resident): void {
            $resident = Homeowner::withTrashed()->with('user')->lockForUpdate()->find($resident->id);
            if (! $resident) {
                return;
            }
            // Serialize property creation, then lock households before their member rows.
            $key = PropertyIdentity::key($resident);
            DB::table('property_address_registry')->insertOrIgnore(['address_key' => $key, 'created_at' => now(), 'updated_at' => now()]);
            DB::table('property_address_registry')->where('address_key', $key)->lockForUpdate()->first();
            $snapshot = HouseholdMember::query()->where('current_homeowner_id', $resident->id)->first();
            $parents = Household::withTrashed()->where(function ($query) use ($snapshot, $resident): void {
                if ($snapshot) {
                    $query->whereKey($snapshot->household_id)->orWhere(fn ($target) => PropertyIdentity::scope($target, $resident));
                } else {
                    PropertyIdentity::scope($query, $resident);
                }
            })
                ->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $propertyKey = PropertyIdentity::confirmedKey($resident);
            $target = $parents->first(fn ($parent): bool => $propertyKey !== null
                ? $parent->property_key === $propertyKey
                : $parent->property_key === null && $parent->address_key === $key);
            $current = HouseholdMember::query()->where('current_homeowner_id', $resident->id)->lockForUpdate()->first();
            $old = $current ? $parents->get($current->household_id) : null;
            if ($current && ! $old) {
                throw ValidationException::withMessages(['block' => __('Household membership changed. Reopen the head review and review all relationships again.')]);
            }
            if ($current && ($resident->trashed() || ! $resident->user || $resident->user->trashed() || ! PropertyIdentity::same($old, $resident))) {
                app(ManageHouseholdHead::class)->detach($old, $current);
                $current->update(['current_homeowner_id' => null, 'ended_at' => now(),
                    'residency_status' => ! PropertyIdentity::same($old, $resident) ? 'Moved' : $current->residency_status]);
                $old->update(['needs_review' => true]);
                $current = null;
            }
            if ($this->reviewReason($resident) !== null) {
                if ($target && ! $target->trashed()) {
                    $target->update(['needs_review' => true]);
                }

                return;
            }
            if ($current) {
                $current->update(['full_name' => $resident->user->full_name]);
                if ($old->registered_homeowner_id === null) {
                    $old->update(['registered_homeowner_id' => app(PropertyDuesAccounts::class)->primaryForAddress(PropertyIdentity::key($resident))?->id]);
                }

                return;
            }
            $household = $target;
            if ($household?->trashed()) {
                throw ValidationException::withMessages(['block' => __('The property household is archived. Ask the HOA to restore it before changing this address.')]);
            }
            $household ??= Household::query()->create([
                ...PropertyAddress::normalizeForStorage($resident->getAttributes()), 'identifier' => (string) Str::uuid(),
                'house_number' => null,
                'street' => $resident->street,
                'address_key' => PropertyIdentity::key($resident),
                'needs_review' => true,
                'registered_homeowner_id' => app(PropertyDuesAccounts::class)->primaryForAddress(PropertyIdentity::key($resident))?->id,
            ]);
            HouseholdMember::query()->create([
                'household_id' => $household->id, 'homeowner_id' => $resident->id, 'current_homeowner_id' => $resident->id,
                'full_name' => $resident->user->full_name, 'relationship' => 'Other', 'residency_status' => 'Resident',
            ]);
        }, 3);
    }
}
