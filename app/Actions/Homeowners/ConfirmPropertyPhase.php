<?php

declare(strict_types=1);

namespace App\Actions\Homeowners;

use App\Models\DuesObligation;
use App\Models\Homeowner;
use App\Models\Household;
use App\Models\HouseholdMember;
use App\Models\User;
use App\Services\PropertyAddressAvailability;
use App\Services\PropertyDuesAccounts;
use App\Support\OperationalAccess;
use App\Support\PropertyIdentity;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class ConfirmPropertyPhase
{
    public function handle(Homeowner $resident, mixed $suffix, User $actor): Homeowner
    {
        OperationalAccess::authorize($actor, 'manage_homeowners');
        $suffix = PropertyIdentity::suffix($suffix);
        Validator::make(['phase_suffix' => $suffix], ['phase_suffix' => ['required', 'regex:/^[A-Z0-9]{1,10}$/D']])->validate();
        $address = [...$resident->getAttributes(), 'phase_suffix' => $suffix];

        return app(PropertyAddressAvailability::class)->withAddressLock($address, $resident->id, function () use ($resident, $suffix): Homeowner {
            $resident = Homeowner::query()->lockForUpdate()->findOrFail($resident->id);
            $address = [...$resident->getAttributes(), 'phase_suffix' => $suffix];
            if (PropertyIdentity::same($resident, $address)) {
                return $resident;
            }
            if ($resident->landlord_homeowner_id || $resident->tenants()->exists()) {
                $this->reject('Unlink or reassign linked tenants before confirming a different property identity.');
            }
            app(PropertyDuesAccounts::class)->assertAssessmentIdentity($address, CarbonImmutable::now()->startOfMonth(), ignoreResidentId: $resident->id);
            $unresolvedOwners = Homeowner::query()->whereNull('property_key')->whereKeyNot($resident->id)
                ->whereIn('ownership_type', ['Owner', 'Co-owner'])->whereIn('status', ['Active', 'Delinquent'])
                ->whereHas('user', fn ($q) => $q->where('account_status', 'Active'))->lockForUpdate()->get();
            if ($unresolvedOwners->contains(fn ($other) => PropertyIdentity::baseKey($other) === PropertyIdentity::baseKey($address))) {
                $this->reject('Other liable accounts at this block and lot have an unconfirmed phase. HOA review is required before changing financial grouping.');
            }
            $target = PropertyIdentity::scope(Homeowner::query(), $address)->whereKeyNot($resident->id)->lockForUpdate()->get();
            if ($resident->ownership_type === 'Owner' && $target->isNotEmpty()) {
                $this->reject('This property is already associated with another account. Ask the HOA to review ownership.');
            }
            if ($target->where('ownership_type', 'Owner')->count() > 1) {
                $this->reject('Multiple Owner records require HOA review.');
            }
            $current = HouseholdMember::where('current_homeowner_id', $resident->id)->lockForUpdate()->first();
            $household = PropertyIdentity::scope(Household::withTrashed(), $address)->lockForUpdate()->first();
            if ($household && ($household->trashed() || ($current && $current->household_id !== $household->id))) {
                $this->reject('Conflicting household identities require HOA review. No households were merged.');
            }
            $ids = $target->modelKeys();
            $ids[] = $resident->id;
            $conflict = DuesObligation::query()->whereIn('homeowner_id', $ids)
                ->where('billing_year', now()->year)->where('billing_month', now()->month)
                ->where('status', '!=', 'Cancelled')->select('dues_setting_id')
                ->groupBy('dues_setting_id')->havingRaw('COUNT(*) > 1')->exists();
            if ($conflict) {
                $this->reject('Current-period obligations conflict. Ask the HOA to review them before confirming this phase.');
            }
            // Adopt the same existing household only if it has no other linked account.
            if ($current && ! $current->household->property_key) {
                if ($current->household->members()->whereNotNull('current_homeowner_id')->where('current_homeowner_id', '!=', $resident->id)->exists()) {
                    $this->reject('This shared legacy household requires HOA review before confirming its phase.');
                }
                $current->household->update(['phase_suffix' => $suffix, 'needs_review' => true]);
            }
            $resident->update(['phase_suffix' => $suffix]);

            return $resident->refresh();
        }, assertAvailable: false);
    }

    private function reject(string $message): never
    {
        throw ValidationException::withMessages(['phase_suffix' => __($message)]);
    }
}
