<?php

declare(strict_types=1);

namespace App\Actions\Dues;

use App\Models\DuesObligation;
use App\Models\DuesSetting;
use App\Services\PropertyAddressAvailability;
use App\Services\PropertyDuesAccounts;
use App\Support\PropertyIdentity;
use App\Support\PropertySnapshot;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final class GenerateSettingObligations
{
    public function __construct(private readonly PropertyDuesAccounts $accounts) {}

    public function handle(DuesSetting $setting, CarbonImmutable $period): int
    {
        // Avoid walking every property for an inactive/non-applicable schedule.
        // This fresh read is only an optimization; each write rechecks under lock.
        $setting = DuesSetting::query()->find($setting->id);
        if ($setting === null || ! $setting->is_active || ! $this->appliesToPeriod($setting, $period)) {
            return 0;
        }

        $created = 0;
        $seenProperties = [];
        $homeowners = $this->accounts->activeOwners()
            ->orderByRaw("CASE WHEN ownership_type = 'Owner' THEN 0 ELSE 1 END")
            ->orderBy('id')
            ->get();

        foreach ($homeowners as $homeowner) {
            $propertyKey = PropertyIdentity::key($homeowner);
            if (isset($seenProperties[$propertyKey])) {
                continue;
            }
            $validCandidate = false;
            $created += DB::transaction(function () use ($homeowner, $setting, $period, $propertyKey, &$validCandidate): int {
                // Schedule first, then property/resident: never assess a stale financial snapshot.
                $setting = DuesSetting::query()->lockForUpdate()->find($setting->id);
                if ($setting === null || ! $setting->is_active || ! $this->appliesToPeriod($setting, $period)) {
                    return 0;
                }
                app(PropertyAddressAvailability::class)->lockProperty($homeowner->getAttributes());
                // The initial candidate list may predate an address move or deactivation.
                $current = $this->accounts->activeOwners()->whereKey($homeowner->id)->lockForUpdate()->first();
                if ($current === null || PropertyIdentity::key($current) !== $propertyKey) {
                    return 0;
                }
                $validCandidate = true;
                $exists = DuesObligation::query()
                    ->where('dues_setting_id', $setting->id)
                    ->where('billing_year', $period->year)
                    ->where('billing_month', $period->month)
                    ->whereHas('homeowner', fn ($query) => PropertyIdentity::scope($query, $homeowner))
                    ->exists();
                if ($exists) {
                    return 0;
                }
                $this->accounts->assertAssessmentIdentity($current->getAttributes(), $period, $setting->id);

                return DuesObligation::query()->insertOrIgnore([
                    'homeowner_id' => $homeowner->id,
                    'dues_setting_id' => $setting->id,
                    'billing_year' => $period->year,
                    'billing_month' => $period->month,
                    'due_date' => $period->day(min((int) ($setting->due_day ?: 10), $period->daysInMonth))->toDateString(),
                    'amount_due' => $setting->amount,
                    'penalty_amount' => '0.00',
                    'amount_paid' => '0.00',
                    'status' => 'Pending',
                    'property_snapshot' => json_encode(PropertySnapshot::from($current), JSON_THROW_ON_ERROR),
                    'property_snapshot_needs_review' => false,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });
            if ($validCandidate) {
                $seenProperties[$propertyKey] = true;
            }
        }

        return $created;
    }

    public function appliesToPeriod(DuesSetting $setting, CarbonImmutable $period): bool
    {
        if ($setting->starts_on && $period->endOfMonth()->isBefore($setting->starts_on)) {
            return false;
        }
        if ($setting->ends_on && $period->startOfMonth()->isAfter($setting->ends_on)) {
            return false;
        }

        return match ($setting->frequency) {
            'Annual' => $period->month === ($setting->starts_on?->month ?? 1),
            'Special Assessment' => $setting->starts_on !== null && $period->isSameMonth($setting->starts_on),
            default => true,
        };
    }
}
