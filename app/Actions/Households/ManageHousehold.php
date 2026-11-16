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
use App\Support\PropertyPhase;
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
        $existing = $id ? Household::query()->findOrFail($id) : null;
        if (! $existing) {
            Validator::make($data, ['house_number' => ['prohibited']])->validate();
        }
        // Never accept a replacement for a retired legacy identity field.
        $data['house_number'] = $existing?->house_number;
        $data['street'] = $existing?->street;
        $data['phase'] = $existing?->phase ?? PropertyAddress::DEFAULT_PHASE;
        if ($existing) {
            $data['phase_suffix'] = $existing->phase_suffix;
        }
        $data = [...$data, ...PropertyAddress::normalizeForStorage($data)];
        $data['house_number'] = filled($data['house_number']) ? $data['house_number'] : null;
        $data['contact_number'] = filled($data['contact_number'] ?? null) ? HomeownerProfileValidator::normalizePhone($data['contact_number']) : null;
        $validated = Validator::make($data, [
            'house_number' => ['nullable', 'string', 'max:50'], 'street' => ['nullable', 'string', 'max:100'],
            'phase_suffix' => $existing ? ['nullable', Rule::in([$existing->phase_suffix])] : PropertyPhase::rules(),
            'block' => ['required', 'string', 'max:20'], 'lot' => ['required', 'string', 'max:20'], 'phase' => ['required', 'string', 'max:50'],
            'contact_number' => ['nullable', 'regex:/^09\d{9}$/'], 'occupancy' => ['nullable', 'string', 'max:255'],
            'registered_homeowner_id' => ['nullable', 'integer'], 'head_member_id' => ['nullable', 'integer'],
        ])->validate();

        return DB::transaction(function () use ($id, $validated, $data, $actor, $existing): Household {
            // A legacy household may retain its resident's historical address_key
            // even though newly created records no longer copy the house number.
            $key = $existing ? PropertyIdentity::key($existing) : PropertyIdentity::key($validated);
            DB::table('property_address_registry')->insertOrIgnore(['address_key' => $key, 'created_at' => now(), 'updated_at' => now()]);
            DB::table('property_address_registry')->where('address_key', $key)->lockForUpdate()->first();
            $record = $id ? Household::query()->lockForUpdate()->findOrFail($id) : null;
            $moved = $record && (PropertyIdentity::confirmedKey($record) !== null
                ? ! PropertyIdentity::same($record, $validated)
                : PropertyAddress::key($record->getAttributes()) !== PropertyAddress::key($validated));
            if ($moved) {
                throw ValidationException::withMessages(['block' => __('A household property identity cannot be moved. Reassign account membership instead.')]);
            }
            $identity = $record ? [...$validated, 'address_key' => $record->address_key] : $validated;
            if (PropertyIdentity::scope(Household::withTrashed(), $identity)->when($id, fn ($q) => $q->whereKeyNot($id))->exists()) {
                throw ValidationException::withMessages(['block' => __('This property already has a household record, including archived records. Open or restore it instead.')]);
            }
            if (filled($validated['registered_homeowner_id'] ?? null)) {
                $candidate = PropertyIdentity::scope(Homeowner::query(), $identity)->whereKey($validated['registered_homeowner_id'])
                    ->whereIn('ownership_type', ['Owner', 'Co-owner'])->whereHas('user', fn ($q) => $q->whereNull('users.deleted_at'))->first();
                if (! $candidate) {
                    throw ValidationException::withMessages(['registered_homeowner_id' => __('Select an Owner or Co-owner at this property.')]);
                }
            }
            $reviewHead = ($data['review_head'] ?? false) === true || ($data['review_head'] ?? false) === 1;
            if (! $reviewHead && (int) ($validated['head_member_id'] ?? $record?->head_member_id) !== (int) $record?->head_member_id) {
                throw ValidationException::withMessages(['head_member_id' => __('Use the household head review to change this assignment.')]);
            }
            if (! $reviewHead && array_key_exists('head_member_id', $validated) && $validated['head_member_id'] === null && $record?->head_member_id !== null) {
                throw ValidationException::withMessages(['head_member_id' => __('Use Clear Household Head to remove this assignment.')]);
            }
            unset($validated['head_member_id']);
            $record ??= new Household(['identifier' => (string) Str::uuid(), 'address_key' => $key]);
            $validated['street'] = $record->exists ? $record->street : null;
            $validated['house_number'] = $record->exists ? $record->house_number : null;
            $record->fill($validated)->save();
            if ($reviewHead) {
                app(ManageHouseholdHead::class)->assign($record->id, $data, $actor);
            }

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
            $isHead = $member->exists && $member->ended_at === null && $household->head_member_id === $member->id;
            $historical = $member->exists && $member->ended_at !== null;
            if (($validated['relationship'] === 'Head' && ! $isHead && ! ($historical && $member->relationship === 'Head'))
                || (($isHead || $historical) && $validated['relationship'] !== $member->relationship)) {
                throw ValidationException::withMessages(['relationship' => __('Use the household head review to change this assignment.')]);
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
            if ($historical && $member->ended_at === null) {
                app(ManageHouseholdHead::class)->detach($household, $member);
                if ($member->relationship === 'Head') {
                    $validated['relationship'] = 'Other';
                    $household->update(['needs_review' => true]);
                }
            }
            $member->fill($validated)->save();
            if (! app(ManageHouseholdHead::class)->eligible($member)) {
                app(ManageHouseholdHead::class)->detach($household, $member);
            }

            return $member;
        });
    }

    public function archive(string $kind, int $id, bool $restore, User $actor): void
    {
        OperationalAccess::authorize($actor, 'manage_homeowners', true);
        $model = $kind === 'household' ? Household::class : ($kind === 'member' ? HouseholdMember::class : null);
        abort_unless($model, 422);
        $parentId = $kind === 'member' ? HouseholdMember::withTrashed()->findOrFail($id)->household_id : null;
        DB::transaction(function () use ($model, $id, $restore, $parentId): void {
            $parent = $parentId ? Household::withTrashed()->lockForUpdate()->findOrFail($parentId) : null;
            $record = $model::withTrashed()->lockForUpdate()->findOrFail($id);
            if ($record instanceof HouseholdMember) {
                if ($record->household_id !== $parentId) {
                    throw ValidationException::withMessages(['member' => __('Household membership changed. Reopen the head review and review all relationships again.')]);
                }
                if ($record->current_homeowner_id || ($restore && ($parent->trashed() || ($record->homeowner_id && ! PropertyIdentity::same($record->homeowner, $parent))))) {
                    throw ValidationException::withMessages(['member' => __('Resolve linked account or archived-household conflicts before this operation.')]);
                }
                if (! $restore) {
                    app(ManageHouseholdHead::class)->detach($parent, $record);
                } elseif ($record->relationship === 'Head') {
                    app(ManageHouseholdHead::class)->detach($parent, $record);
                    if ($record->ended_at === null) {
                        $record->update(['relationship' => 'Other']);
                    }
                    $parent->update(['needs_review' => true]);
                }
            } elseif (! $restore && $record->members()->whereNull('ended_at')->lockForUpdate()->get(['id'])->isNotEmpty()) {
                throw ValidationException::withMessages(['household' => __('Reassign or archive current members before archiving the household.')]);
            }
            $restore ? $record->restore() : $record->delete();
        }, 3);
    }
}
