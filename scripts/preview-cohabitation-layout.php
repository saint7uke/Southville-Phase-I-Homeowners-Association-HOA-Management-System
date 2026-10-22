<?php

declare(strict_types=1);

// Synthetic file-only samples for engineering review, never HOA approval or official issuance.
use App\Services\CohabitationCertificateSource;
use App\Services\OriginalCertificateRenderer;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Storage;

require dirname(__DIR__).'/vendor/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$directory = $argv[1] ?? storage_path('framework/testing/cohabitation-phase-review');
config(['filesystems.disks.local' => ['driver' => 'local', 'root' => $directory, 'throw' => true]]);
Storage::forgetDisk('local');
$layout = app(CohabitationCertificateSource::class)->manifest();
Storage::disk('local')->put($layout['background_path'], app(CohabitationCertificateSource::class)->pdf());
$snapshot = ['layout' => $layout, 'cohabitation' => ['cohabitant_one' => 'José Dela Cruz', 'cohabitant_two' => 'María Santos',
    'cohabitant_one_birth_date' => '1990-01-02', 'cohabitant_two_birth_date' => '1991-02-03',
    'address' => ['block' => '1', 'lot' => '2', 'street' => 'Test Street', 'phase_suffix' => 'B'],
    'years_together' => 5, 'requester' => 'one']];
$renderer = app(OriginalCertificateRenderer::class);
$values = $renderer->values($snapshot, CarbonImmutable::parse('2026-10-21'));
foreach (['blank' => [[], false], 'filled' => [$values, false], 'draft' => [$values, true]] as $name => [$fields, $draft]) {
    Storage::disk('local')->put($name.'.pdf', $renderer->render($layout, $fields, $draft));
}
echo 'Synthetic review PDFs: '.$directory.PHP_EOL;
