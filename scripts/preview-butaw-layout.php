<?php

declare(strict_types=1);

// File-only engineering review. No database access, source approval or official generation.
use App\Services\ButawReceiptSource;
use App\Services\OriginalCertificateRenderer;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Storage;

require dirname(__DIR__).'/vendor/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$directory = storage_path('framework/testing/butaw-layout-review');
config(['filesystems.disks.local' => ['driver' => 'local', 'root' => $directory, 'throw' => true]]);
Storage::forgetDisk('local');
$layout = app(ButawReceiptSource::class)->manifest();
Storage::disk('local')->put($layout['background_path'], app(ButawReceiptSource::class)->originalPdf());
$values = ['amount' => '489.00', 'month' => 'October', 'block' => '71', 'lot' => '10', 'collector' => 'José Dela Cruz'];
foreach (['blank' => [[], false], 'filled' => [$values, false], 'draft' => [$values, true]] as $name => [$fields, $draft]) {
    Storage::disk('local')->put($name.'.pdf', app(OriginalCertificateRenderer::class)->render($layout, $fields, $draft));
}
echo 'Synthetic populated review copies only: '.$directory.PHP_EOL;
