<?php

declare(strict_types=1);

namespace App\Actions\Dues;

use App\Models\DuesSetting;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final class CloseDuesSetting
{
    public function handle(DuesSetting $setting, User $actor): DuesSetting
    {
        Gate::forUser($actor)->authorize('update', $setting);

        return DB::transaction(function () use ($setting): DuesSetting {
            $locked = DuesSetting::query()->lockForUpdate()->findOrFail($setting->id);
            $latest = $locked->obligations()->orderByDesc('billing_year')->orderByDesc('billing_month')->first();
            $latestMonth = $latest
                ? CarbonImmutable::create($latest->billing_year, $latest->billing_month)->endOfMonth()
                : CarbonImmutable::parse($locked->starts_on)->endOfMonth();
            $closedOn = $latestMonth->max(now()->toImmutable()->endOfMonth());

            $locked->update(['is_active' => false, 'ends_on' => $closedOn->toDateString()]);

            return $locked->refresh();
        });
    }
}
