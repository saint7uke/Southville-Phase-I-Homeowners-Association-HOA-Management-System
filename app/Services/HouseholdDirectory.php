<?php

declare(strict_types=1);

namespace App\Services;

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
        foreach (['street', 'block', 'lot', 'phase'] as $field) {
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
            $current = HouseholdMember::query()->where('current_homeowner_id', $resident->id)->lockForUpdate()->first();
            if ($current && ($resident->trashed() || ! $resident->user || $resident->user->trashed() || ! PropertyIdentity::same($current->household, $resident))) {
                $old = $current->household;
                $current->update(['current_homeowner_id' => null, 'ended_at' => now(),
                    'residency_status' => ! PropertyIdentity::same($old, $resident) ? 'Moved' : $current->residency_status]);
                $old->update(['needs_review' => true]);
                $current = null;
            }
            if ($this->reviewReason($resident) !== null) {
                if ($existing = PropertyIdentity::scope(Household::query(), $resident)->first()) {
                    $existing->update(['needs_review' => true]);
                }

                return;
            }
            if ($current) {
                $current->update(['full_name' => $resident->user->full_name]);
                if ($current->household->registered_homeowner_id === null) {
                    $current->household->update(['registered_homeowner_id' => app(PropertyDuesAccounts::class)->primaryForAddress(PropertyIdentity::key($resident))?->id]);
                }

                return;
            }
            DB::table('property_address_registry')->insertOrIgnore(['address_key' => PropertyIdentity::key($resident), 'created_at' => now(), 'updated_at' => now()]);
            DB::table('property_address_registry')->where('address_key', PropertyIdentity::key($resident))->lockForUpdate()->first();
            $household = PropertyIdentity::scope(Household::withTrashed(), $resident)->lockForUpdate()->first();
            if ($household?->trashed()) {
                throw ValidationException::withMessages(['house_number' => __('The property household is archived. Ask the HOA to restore it before changing this address.')]);
            }
            $household ??= Household::query()->create([
                ...PropertyAddress::normalizeForStorage($resident->getAttributes()), 'identifier' => (string) Str::uuid(),
                'house_number' => $resident->house_number,
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
