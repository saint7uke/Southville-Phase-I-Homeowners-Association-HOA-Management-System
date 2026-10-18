<?php

declare(strict_types=1);

namespace App\Actions\Dues;

use App\Models\DuesObligation;
use App\Models\DuesSetting;
use App\Services\PropertyDuesAccounts;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final class GenerateSettingObligations
{
    public function __construct(private readonly PropertyDuesAccounts $accounts) {}

    public function handle(DuesSetting $setting, CarbonImmutable $period): int
    {
        if (! $setting->is_active || ! $this->appliesToPeriod($setting, $period)) {
            return 0;
        }

        $created = 0;
        $seenProperties = [];
        $homeowners = $this->accounts->activeOwners()
            ->orderByRaw("CASE WHEN ownership_type = 'Owner' THEN 0 ELSE 1 END")
            ->orderBy('id')
            ->get(['id', 'address_key']);

        foreach ($homeowners as $homeowner) {
            if (isset($seenProperties[$homeowner->address_key])) {
                continue;
            }
            $seenProperties[$homeowner->address_key] = true;
            $created += DB::transaction(function () use ($homeowner, $setting, $period): int {
                DB::table('property_address_registry')->insertOrIgnore([
                    'address_key' => $homeowner->address_key,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                DB::table('property_address_registry')->where('address_key', $homeowner->address_key)->lockForUpdate()->first();
                $exists = DuesObligation::query()
                    ->where('dues_setting_id', $setting->id)
                    ->where('billing_year', $period->year)
                    ->where('billing_month', $period->month)
                    ->whereHas('homeowner', fn ($query) => $query->where('address_key', $homeowner->address_key))
                    ->exists();
                if ($exists) {
                    return 0;
                }

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
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });
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
