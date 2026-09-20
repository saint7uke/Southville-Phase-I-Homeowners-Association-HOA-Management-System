<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('hoa:generate-dues')->monthlyOn(1, '00:05')->withoutOverlapping();
Schedule::command('hoa:mark-overdue-dues')->dailyAt('00:15')->withoutOverlapping();
Schedule::command('hoa:flag-delinquent-homeowners')->dailyAt('00:20')->withoutOverlapping();
Schedule::command('hoa:prune-report-exports')->dailyAt('00:30')->withoutOverlapping();
