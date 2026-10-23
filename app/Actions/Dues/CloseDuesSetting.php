<?php

declare(strict_types=1);

namespace App\Actions\Dues;

use App\Models\DuesSetting;
use App\Models\User;
use App\Services\DuesSchedulePeriods;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final class CloseDuesSetting
{
    public function handle(DuesSetting $setting, User $actor): DuesSetting
    {
        Gate::forUser($actor)->authorize('update', $setting);
        $expectedName = $setting->fresh()->normalized_name;

        return DB::transaction(function () use ($setting, $expectedName): DuesSetting {
            app(DuesSchedulePeriods::class)->lockNames([$expectedName]);
            $locked = DuesSetting::query()->lockForUpdate()->findOrFail($setting->id);
            if ($locked->normalized_name !== $expectedName) {
                throw ValidationException::withMessages(['name' => __('This dues schedule changed while you were editing. Reload it before making changes.')]);
            }
            $latest = $locked->obligations()->orderByDesc('billing_year')->orderByDesc('billing_month')->first();
            $latestMonth = $latest
                ? CarbonImmutable::create($latest->billing_year, $latest->billing_month)->endOfMonth()
                : CarbonImmutable::parse($locked->starts_on)->endOfMonth();
            $closedOn = $latestMonth->max(now()->toImmutable()->endOfMonth());
            if ($locked->ends_on !== null) {
                if ($latest && $latestMonth->startOfMonth()->isAfter($locked->ends_on)) {
                    throw ValidationException::withMessages(['ends_on' => __('Generated obligations extend beyond this schedule\'s end. Contact the HOA for review; no records were changed.')]);
                }
                $closedOn = $closedOn->min($locked->ends_on->toImmutable());
            }
            app(DuesSchedulePeriods::class)->lockAndAssertNoOverlap([
                'normalized_name' => $locked->normalized_name,
                'starts_on' => $locked->starts_on,
                'ends_on' => $closedOn,
            ], $locked->id);

            $locked->update(['is_active' => false, 'ends_on' => $closedOn->toDateString()]);

            return $locked->refresh();
        });
    }
}
