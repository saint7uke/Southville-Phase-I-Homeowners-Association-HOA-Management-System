<?php

declare(strict_types=1);

namespace App\Support;

use Carbon\CarbonInterface;

final class TimeGreeting
{
    public static function current(): string
    {
        return self::forHour(now(config('app.timezone'))->hour);
    }

    public static function forHour(int $hour): string
    {
        return match (true) {
            $hour >= 5 && $hour < 12 => 'Good morning',
            $hour >= 12 && $hour < 17 => 'Good afternoon',
            default => 'Good evening',
        };
    }

    public static function forDate(CarbonInterface $date): string
    {
        return self::forHour($date->setTimezone(config('app.timezone'))->hour);
    }
}
