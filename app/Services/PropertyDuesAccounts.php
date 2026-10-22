<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Homeowner;
use App\Support\PropertyIdentity;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class PropertyDuesAccounts
{
    public const ASSESSMENT_REVIEW_MESSAGE = 'An unconfirmed legacy property has an assessment for this billing month. Ask the HOA to review it before confirming the phase or creating another assessment.';

    /** Preserve legacy assessments, including those belonging to archived accounts. */
    public function assertAssessmentIdentity(array $address, CarbonImmutable $period, ?int $settingId = null, ?int $ignoreResidentId = null): void
    {
        if (PropertyIdentity::confirmedKey($address) === null) {
            return;
        }
        // A locking joined read sees commits made while waiting for the base mutex,
        // even under MySQL REPEATABLE READ. No historical rows are rewritten.
        $legacy = DB::table('dues_obligations as obligations')->join('homeowners as residents', 'residents.id', '=', 'obligations.homeowner_id')
            ->whereNull('residents.property_key')->where('obligations.status', '!=', 'Cancelled')
            ->where('obligations.billing_year', $period->year)->where('obligations.billing_month', $period->month)
            ->when($settingId !== null, fn ($query) => $query->where('obligations.dues_setting_id', $settingId))
            ->when($ignoreResidentId !== null, fn ($query) => $query->where('residents.id', '!=', $ignoreResidentId))
            ->select(['residents.phase', 'residents.block', 'residents.lot'])->lockForUpdate()->get();
        if ($legacy->contains(fn ($row) => PropertyIdentity::baseKey((array) $row) === PropertyIdentity::baseKey($address))) {
            throw ValidationException::withMessages(['phase_suffix' => __(self::ASSESSMENT_REVIEW_MESSAGE)]);
        }
    }

    public function primaryForAddress(string $addressKey): ?Homeowner
    {
        return $this->activeOwners()
            ->where(fn (Builder $query) => $query->where('property_key', $addressKey)->orWhere(fn (Builder $legacy) => $legacy->whereNull('property_key')->where('address_key', $addressKey)))
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

        if (! PropertyIdentity::same($payer, $liable)) {
            return false;
        }

        return $payer->ownership_type === 'Co-owner'
            || ($payer->ownership_type === 'Tenant' && $payer->landlord_homeowner_id === $liable->id);
    }

    /** @return Builder<Homeowner> */
    public function eligiblePayers(Homeowner $liable): Builder
    {
        return PropertyIdentity::scope(Homeowner::query(), $liable)
            ->whereIn('status', ['Active', 'Delinquent'])
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
