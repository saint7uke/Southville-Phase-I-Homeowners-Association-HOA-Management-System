<?php

declare(strict_types=1);

// Synthetic, file-only engineering previews. No database connection or template activation.
use App\Services\OriginalCertificateRenderer;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Storage;

require dirname(__DIR__).'/vendor/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$directory = storage_path('framework/testing/residency-layout-review');
if (! is_dir($directory)) {
    mkdir($directory, 0700, true);
}
config(['filesystems.disks.residency_review' => ['driver' => 'local', 'root' => $directory, 'throw' => true]]);
// The renderer's local disk is isolated to this process; .env and live private storage are untouched.
config(['filesystems.disks.local' => ['driver' => 'local', 'root' => $directory, 'throw' => true]]);
Storage::forgetDisk('local');
$layout = json_decode(file_get_contents(resource_path('certificates/residency-2026/layout.json')), true, flags: JSON_THROW_ON_ERROR);
$layout['background_path'] = 'certificate-backgrounds/'.$layout['sha256'].'.pdf';
Storage::disk('local')->put($layout['background_path'], file_get_contents(resource_path('certificates/residency-2026/reference.pdf')));
$renderer = app(OriginalCertificateRenderer::class);
$values = ['resident_name' => 'José Dela Cruz', 'block' => '12', 'lot' => '34', 'purpose' => 'Barangay clearance', 'day' => '20', 'month' => 'October'];
foreach (['blank' => [[], false], 'filled' => [$values, false], 'draft' => [$values, true]] as $name => [$fields, $draft]) {
    Storage::disk('residency_review')->put($name.'.pdf', $renderer->render($layout, $fields, $draft));
}
echo 'Synthetic review PDFs: '.$directory.PHP_EOL;
