<?php

declare(strict_types=1);

namespace App\Actions\Households;

use App\Models\Household;
use App\Models\HouseholdMember;
use App\Models\User;
use App\Support\OperationalAccess;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class ManageHouseholdHead
{
    public const RELATIONSHIPS = ['Spouse', 'Partner', 'Child', 'Parent', 'Sibling', 'Relative', 'Other'];

    public const RESIDENCY = ['Resident', 'Temporarily away'];

    public function eligible(HouseholdMember $member): bool
    {
        return ! $member->trashed() && $member->ended_at === null && in_array($member->residency_status, self::RESIDENCY, true);
    }

    public function revision(Household $household, ?Collection $members = null): string
    {
        $fields = ['id', 'household_id', 'full_name', 'relationship', 'residency_status', 'ended_at', 'deleted_at', 'current_homeowner_id'];
        $members ??= $household->members()->withTrashed()->orderBy('id')->get($fields);
        $state = $members->sortBy('id')->map(fn (HouseholdMember $member): array => $member->only($fields))->values()->all();

        return hash('sha256', json_encode([$household->head_member_id, $household->deleted_at, $state], JSON_THROW_ON_ERROR));
    }

    /** Read-only: never reconcile a legacy conflict while rendering a page. */
    public function status(Household $household): array
    {
        $head = $household->head_member_id ? $household->members()->whereKey($household->head_member_id)
            ->whereNull('ended_at')->whereIn('residency_status', self::RESIDENCY)->first(['id', 'full_name', 'relationship']) : null;
        $heads = $household->members()->whereNull('ended_at')->where('relationship', 'Head');
        $conflict = $household->head_member_id !== null
            ? (! $head || $head->relationship !== 'Head' || (clone $heads)->whereKeyNot($household->head_member_id)->exists())
            : $heads->exists();

        return ['head_name' => $head?->full_name, 'conflict' => $conflict,
            'warning' => $conflict ? __('Household head and relationship records conflict. HOA review is required.')
                : ($head === null ? __('No household head assigned. Relationships require HOA review.')
                    : ($household->needs_review ? __('Household information requires HOA review.') : null))];
    }

    public function assign(int $id, array $data, User $actor): Household
    {
        OperationalAccess::authorize($actor, 'manage_homeowners');
        $data = Validator::make($data, [
            'head_member_id' => ['required', 'integer', 'min:1'], 'head_revision' => ['required', 'string', 'size:64'],
            'relationships' => ['required', 'array'], 'relationships.*' => ['required', 'string', Rule::in(['Head', ...self::RELATIONSHIPS])],
            'review_confirmed' => ['accepted'],
        ])->validate();

        return DB::transaction(function () use ($id, $data): Household {
            $household = Household::query()->lockForUpdate()->findOrFail($id);
            $members = $household->members()->withTrashed()->orderBy('id')->lockForUpdate()->get();
            $this->checkRevision($household, $data['head_revision'], $members);
            $current = $members->filter(fn ($member): bool => ! $member->trashed() && $member->ended_at === null);
            $head = $current->firstWhere('id', (int) $data['head_member_id']);
            if (! $head || ! $this->eligible($head)) {
                throw ValidationException::withMessages(['head_member_id' => __('Select an eligible current member of this household.')]);
            }
            $expected = $current->modelKeys();
            $provided = array_keys($data['relationships']);
            sort($expected);
            sort($provided);
            if (array_map('strval', $expected) !== array_map('strval', $provided)) {
                throw ValidationException::withMessages(['relationships' => __('Review every current member before assigning the household head.')]);
            }
            foreach ($current as $member) {
                $relationship = $data['relationships'][$member->id];
                if (($member->id === $head->id) !== ($relationship === 'Head')) {
                    throw ValidationException::withMessages(['relationships.'.$member->id => __('Only the selected household head may have the Head relationship.')]);
                }
            }
            foreach ($current as $member) {
                $member->update(['relationship' => $data['relationships'][$member->id]]);
            }
            // Assignment confirms relationships, not unrelated property/application review issues.
            $household->update(['head_member_id' => $head->id]);

            return $household->refresh();
        }, 3);
    }

    public function clear(int $id, mixed $revision, User $actor): Household
    {
        OperationalAccess::authorize($actor, 'manage_homeowners');
        $revision = Validator::make(['head_revision' => $revision], ['head_revision' => ['required', 'string', 'size:64']])->validate()['head_revision'];

        return DB::transaction(function () use ($id, $revision): Household {
            $household = Household::query()->lockForUpdate()->findOrFail($id);
            $members = $household->members()->withTrashed()->orderBy('id')->lockForUpdate()->get();
            $this->checkRevision($household, $revision, $members);
            foreach ($members as $member) {
                if (! $member->trashed() && $member->ended_at === null && $member->relationship === 'Head') {
                    $member->update(['relationship' => 'Other']);
                }
            }
            $household->update(['head_member_id' => null, 'needs_review' => true]);

            return $household->refresh();
        }, 3);
    }

    /** Caller holds the household lock before changing its membership. Historical roles stay intact. */
    public function detach(Household $household, HouseholdMember $member): void
    {
        if ($household->head_member_id === $member->id) {
            $household->update(['head_member_id' => null, 'needs_review' => true]);
        }
    }

    private function checkRevision(Household $household, string $revision, Collection $members): void
    {
        // Compare the locked current read, not an older repeatable-read snapshot.
        if (! hash_equals($this->revision($household, $members), $revision)) {
            throw ValidationException::withMessages(['head_revision' => __('Household membership changed. Reopen the head review and review all relationships again.')]);
        }
    }
}
