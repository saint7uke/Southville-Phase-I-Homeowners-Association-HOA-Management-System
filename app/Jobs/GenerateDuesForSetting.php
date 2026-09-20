<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\Dues\GenerateSettingObligations;
use App\Models\DuesSetting;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

final class GenerateDuesForSetting implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public readonly int $duesSettingId, public readonly string $period)
    {
        $this->afterCommit();
    }

    public function handle(GenerateSettingObligations $generator): void
    {
        $setting = DuesSetting::query()->find($this->duesSettingId);
        if ($setting !== null) {
            $generator->handle($setting, CarbonImmutable::parse($this->period)->startOfMonth());
        }
    }
}
