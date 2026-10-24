<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Household;
use App\Models\HouseholdMember;
use App\Models\User;
use App\Support\PropertyIdentity;
use Illuminate\Database\Eloquent\Builder;

final class OwnHouseholdSummary
{
    public function forUser(User $user): ?array
    {
        $household = $this->householdFor($user);
        if (! $household) {
            return null;
        }

        return ['id' => $household->id, 'identifier' => $household->identifier, 'address' => $household->full_address, 'needs_review' => $household->needs_review,
            'members' => $this->membersQuery($household)->get()->map(fn ($record) => $record->only(['full_name', 'relationship', 'residency_status']))->all()];
    }

    public function householdFor(?User $user): ?Household
    {
        // Reload account and membership state, including during subsequent Livewire requests.
        $user = $user ? User::query()->with('homeowner')->find($user->id) : null;
        if (! $user || $user->account_status !== 'Active' || ! $user->hasRole('homeowner') || ! $user->hasVerifiedEmail() || ! ($resident = $user->homeowner)) {
            return null;
        }

        $member = HouseholdMember::query()->with('household')->where('current_homeowner_id', $resident->id)->whereNull('ended_at')->first();
        $household = $member?->household;
        if (! $household || $household->trashed() || ! PropertyIdentity::same($household, $resident)) {
            return null;
        }

        return $household;
    }

    /** The caller must first resolve the authorized household through householdFor(). */
    public function membersQuery(?Household $household): Builder
    {
        return HouseholdMember::query()
            ->select(['id', 'household_id', 'full_name', 'relationship', 'residency_status'])
            ->where('household_id', $household?->id)->whereNull('ended_at')
            ->when($household === null, fn (Builder $query): Builder => $query->whereRaw('1 = 0'));
    }
}
