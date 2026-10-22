<?php

use App\Models\CertificatePreview;
use App\Models\PaymentDocumentPreview;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Schema;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('hoa:generate-dues')->monthlyOn(1, '00:05')->withoutOverlapping();
Schedule::command('hoa:mark-overdue-dues')->dailyAt('00:15')->withoutOverlapping();
Schedule::command('hoa:flag-delinquent-homeowners')->dailyAt('00:20')->withoutOverlapping();
Schedule::command('hoa:prune-report-exports')->dailyAt('00:30')->withoutOverlapping();
Schedule::command('model:prune', ['--model' => [CertificatePreview::class]])->dailyAt('00:40')
    ->when(fn () => Schema::hasTable('certificate_previews'))->withoutOverlapping();
Schedule::command('model:prune', ['--model' => [PaymentDocumentPreview::class]])->dailyAt('00:45')
    ->when(fn () => Schema::hasTable('payment_document_previews'))->withoutOverlapping();
