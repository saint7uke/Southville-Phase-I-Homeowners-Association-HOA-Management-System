<?php

declare(strict_types=1);

namespace App\Actions\Households;

use App\Models\Homeowner;
use App\Models\Household;
use App\Models\HouseholdMember;
use App\Models\User;
use App\Support\OperationalAccess;
use App\Support\PersonName;
use App\Support\PropertyAddress;
use App\Support\PropertyIdentity;
use App\Validation\HomeownerProfileValidator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class ManageHousehold
{
    public const RELATIONSHIPS = ['Head', 'Spouse', 'Partner', 'Child', 'Parent', 'Sibling', 'Relative', 'Other'];

    public const RESIDENCY = ['Resident', 'Temporarily away', 'Moved'];

    public function save(?int $id, array $data, User $actor): Household
    {
        OperationalAccess::authorize($actor, 'manage_homeowners');
        $data = [...$data, ...PropertyAddress::normalizeForStorage($data)];
        $data['house_number'] = filled($data['house_number']) ? $data['house_number'] : null;
        $data['contact_number'] = filled($data['contact_number'] ?? null) ? HomeownerProfileValidator::normalizePhone($data['contact_number']) : null;
        $validated = Validator::make($data, [
            'house_number' => ['nullable', 'string', 'max:50'], 'street' => ['required', 'string', 'max:100'],
            'phase_suffix' => ['nullable', 'regex:/^[A-Z0-9]{1,10}$/D'],
            'block' => ['required', 'string', 'max:20'], 'lot' => ['required', 'string', 'max:20'], 'phase' => ['required', 'string', 'max:50'],
            'contact_number' => ['nullable', 'regex:/^09\d{9}$/'], 'occupancy' => ['nullable', 'string', 'max:255'],
            'registered_homeowner_id' => ['nullable', 'integer'], 'head_member_id' => ['nullable', 'integer'],
        ])->validate();

        return DB::transaction(function () use ($id, $validated): Household {
            $key = PropertyIdentity::key($validated);
            DB::table('property_address_registry')->insertOrIgnore(['address_key' => $key, 'created_at' => now(), 'updated_at' => now()]);
            DB::table('property_address_registry')->where('address_key', $key)->lockForUpdate()->first();
            $record = $id ? Household::query()->lockForUpdate()->findOrFail($id) : null;
            if ($record && ! PropertyIdentity::same($record, $validated)) {
                throw ValidationException::withMessages(['house_number' => __('A household property identity cannot be moved. Reassign account membership instead.')]);
            }
            if (PropertyIdentity::scope(Household::withTrashed(), $validated)->when($id, fn ($q) => $q->whereKeyNot($id))->exists()) {
                throw ValidationException::withMessages(['house_number' => __('This property already has a household record, including archived records. Open or restore it instead.')]);
            }
            if (filled($validated['registered_homeowner_id'] ?? null)) {
                $candidate = PropertyIdentity::scope(Homeowner::query(), $validated)->whereKey($validated['registered_homeowner_id'])
                    ->whereIn('ownership_type', ['Owner', 'Co-owner'])->whereHas('user', fn ($q) => $q->whereNull('users.deleted_at'))->first();
                if (! $candidate) {
                    throw ValidationException::withMessages(['registered_homeowner_id' => __('Select an Owner or Co-owner at this property.')]);
                }
            }
            if (filled($validated['head_member_id'] ?? null) && (! $record || ! $record->members()->whereKey($validated['head_member_id'])->whereNull('ended_at')->exists())) {
                throw ValidationException::withMessages(['head_member_id' => __('Select a current member of this household.')]);
            }
            $record ??= new Household(['identifier' => (string) Str::uuid(), 'address_key' => $key]);
            $record->fill($validated)->save();
            $record->update(['needs_review' => false]);

            return $record->refresh();
        }, 3);
    }

    public function saveMember(int $householdId, ?int $id, array $data, User $actor): HouseholdMember
    {
        OperationalAccess::authorize($actor, 'manage_homeowners');
        $data['full_name'] = PersonName::normalize($data['full_name'] ?? null);
        $validated = Validator::make($data, [
            'full_name' => ['required', 'string', 'max:255', "regex:/^[\pL\s'.-]+$/u"],
            'relationship' => ['required', Rule::in(self::RELATIONSHIPS)], 'date_of_birth' => ['nullable', 'date', 'before:today'],
            'sex' => ['nullable', Rule::in(['Male', 'Female', 'Prefer not to say'])], 'residency_status' => ['required', Rule::in(self::RESIDENCY)],
            'household_id' => ['prohibited'], 'homeowner_id' => ['prohibited'], 'current_homeowner_id' => ['prohibited'], 'ended_at' => ['prohibited'],
        ])->validate();

        return DB::transaction(function () use ($householdId, $id, $validated): HouseholdMember {
            $household = Household::query()->lockForUpdate()->findOrFail($householdId);
            $member = $id ? $household->members()->lockForUpdate()->findOrFail($id) : new HouseholdMember(['household_id' => $household->id]);
            if ($member->current_homeowner_id && PersonName::key($validated['full_name']) !== PersonName::key($member->homeowner->user->full_name)) {
                throw ValidationException::withMessages(['full_name' => __('Update the linked account name through its profile, not the census.')]);
            }
            if ($validated['relationship'] === 'Head' && $household->head_member_id !== $member->id) {
                throw ValidationException::withMessages(['relationship' => __('Select the household head in the household form first.')]);
            }
            if ($member->current_homeowner_id && $validated['residency_status'] === 'Moved') {
                throw ValidationException::withMessages(['residency_status' => __('Change the linked account address before marking its membership as moved.')]);
            }
            if ($member->homeowner_id && $member->ended_at && $validated['residency_status'] !== 'Moved') {
                throw ValidationException::withMessages(['residency_status' => __('Ended account memberships are historical. Reassign the account through its current address instead.')]);
            }
            if (! $member->homeowner_id) {
                $member->ended_at = $validated['residency_status'] === 'Moved' ? ($member->ended_at ?? now()) : null;
            }
            $member->fill($validated)->save();

            return $member;
        });
    }

    public function archive(string $kind, int $id, bool $restore, User $actor): void
    {
        OperationalAccess::authorize($actor, 'manage_homeowners', true);
        DB::transaction(function () use ($kind, $id, $restore): void {
            $model = $kind === 'household' ? Household::class : ($kind === 'member' ? HouseholdMember::class : null);
            abort_unless($model, 422);
            $record = $model::withTrashed()->lockForUpdate()->findOrFail($id);
            if ($record instanceof HouseholdMember) {
                $parent = Household::withTrashed()->lockForUpdate()->findOrFail($record->household_id);
                if ($record->current_homeowner_id || ($restore && ($parent->trashed() || ($record->homeowner_id && ! PropertyIdentity::same($record->homeowner, $parent))))) {
                    throw ValidationException::withMessages(['member' => __('Resolve linked account or archived-household conflicts before this operation.')]);
                }
                if ($parent->head_member_id === $record->id && ! $restore) {
                    $parent->update(['head_member_id' => null, 'needs_review' => true]);
                }
            } elseif (! $restore && $record->members()->whereNull('ended_at')->exists()) {
                throw ValidationException::withMessages(['household' => __('Reassign or archive current members before archiving the household.')]);
            }
            $restore ? $record->restore() : $record->delete();
        });
    }
}
