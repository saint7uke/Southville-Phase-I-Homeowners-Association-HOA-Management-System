<?php

declare(strict_types=1);

namespace App\Actions\Dues;

use App\Models\DuesSetting;
use Carbon\CarbonImmutable;

final class GenerateMonthlyObligations
{
    public function handle(?CarbonImmutable $period = null): int
    {
        $period ??= now()->toImmutable()->startOfMonth();
        $created = 0;

        DuesSetting::query()
            ->where('is_active', true)
            ->orderBy('id')
            ->each(function (DuesSetting $setting) use ($period, &$created): void {
                $created += app(GenerateSettingObligations::class)->handle($setting, $period);
            });

        return $created;
    }
}
