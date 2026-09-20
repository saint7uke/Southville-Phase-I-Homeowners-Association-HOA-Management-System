<?php

declare(strict_types=1);

namespace App\Actions\Dues;

use App\Models\DuesObligation;
use App\Models\Homeowner;
use App\Models\SystemSetting;
use App\Notifications\HomeownerDelinquencyNotice;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

final class FlagDelinquentHomeowners
{
    public function handle(?CarbonImmutable $today = null): int
    {
        $today ??= now()->toImmutable()->startOfDay();
        $settings = SystemSetting::current();
        $threshold = max(1, min(12, $settings->delinquency_months));
        $periods = collect(range(0, $threshold - 1))->map(fn (int $offset): CarbonImmutable => $today->startOfMonth()->subMonths($offset));

        $homeownerIds = DuesObligation::query()
            ->where('status', 'Overdue')
            ->where(function (Builder $query) use ($periods): void {
                foreach ($periods as $period) {
                    $query->orWhere(fn (Builder $periodQuery): Builder => $periodQuery
                        ->where('billing_year', $period->year)
                        ->where('billing_month', $period->month));
                }
            })
            ->groupBy('homeowner_id')
            ->havingRaw('COUNT(DISTINCT (billing_year * 100 + billing_month)) >= ?', [$threshold])
            ->pluck('homeowner_id');

        $changed = 0;
        Homeowner::query()->with('user')->whereIn('id', $homeownerIds)->where('status', 'Active')->chunkById(100, function ($homeowners) use (&$changed, $settings, $threshold): void {
            foreach ($homeowners as $homeowner) {
                $homeowner->update(['status' => 'Delinquent']);
                $changed++;

                if ($settings->delinquency_notifications) {
                    $homeowner->user->notify(new HomeownerDelinquencyNotice($threshold));
                }
            }
        });

        return $changed;
    }
}
