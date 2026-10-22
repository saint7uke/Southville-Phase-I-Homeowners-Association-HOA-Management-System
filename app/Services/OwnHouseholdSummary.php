<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\HouseholdMember;
use App\Models\User;
use App\Support\PropertyIdentity;

final class OwnHouseholdSummary
{
    public function forUser(User $user): ?array
    {
        if ($user->trashed() || $user->account_status !== 'Active' || ! $user->hasRole('homeowner') || ! $user->hasVerifiedEmail() || ! ($resident = $user->homeowner)) {
            return null;
        }
        $member = HouseholdMember::query()->with('household')->where('current_homeowner_id', $resident->id)->first();
        $household = $member?->household;
        if (! $household || $household->trashed() || ! PropertyIdentity::same($household, $resident)) {
            return null;
        }

        return ['id' => $household->id, 'identifier' => $household->identifier, 'address' => $household->full_address, 'needs_review' => $household->needs_review,
            'members' => $household->members()->whereNull('ended_at')->get(['full_name', 'relationship', 'residency_status'])->map(fn ($record) => $record->only(['full_name', 'relationship', 'residency_status']))->all()];
    }
}
