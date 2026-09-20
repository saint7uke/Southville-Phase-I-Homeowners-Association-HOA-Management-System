<?php

declare(strict_types=1);

namespace App\Actions\Dues;

use App\Models\DuesObligation;
use App\Models\DuesSetting;
use App\Models\Homeowner;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

final class GenerateSettingObligations
{
    public function handle(DuesSetting $setting, CarbonImmutable $period): int
    {
        if (! $setting->is_active || ! $this->appliesToPeriod($setting, $period)) {
            return 0;
        }

        $created = 0;
        Homeowner::query()->where('status', 'Active')->select('id')->orderBy('id')
            ->chunkById(200, function (Collection $homeowners) use ($setting, $period, &$created): void {
                foreach ($homeowners as $homeowner) {
                    $created += DuesObligation::query()->insertOrIgnore([
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
                }
            });

        return $created;
    }

    private function appliesToPeriod(DuesSetting $setting, CarbonImmutable $period): bool
    {
        if ($setting->starts_on && $period->endOfMonth()->isBefore($setting->starts_on)) {
            return false;
        }
        if ($setting->ends_on && $period->startOfMonth()->isAfter($setting->ends_on)) {
            return false;
        }

        return match ($setting->frequency) {
            'Annual' => $period->month === ($setting->starts_on?->month ?? 1),
            'Special Assessment' => $setting->starts_on === null || $period->isSameMonth($setting->starts_on),
            default => true,
        };
    }
}
