<?php

declare(strict_types=1);
use App\Services\ResidencyFont;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Http;

// Called by the isolated local runner only; never boot production configuration.
if (getenv('APP_ENV') !== 'testing' || getenv('DB_CONNECTION') !== 'sqlite' || getenv('DB_DATABASE') !== ':memory:') {
    throw new RuntimeException('Font verification requires the isolated SQLite testing environment.');
}
require dirname(__DIR__).'/tests/bootstrap.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (! $app->environment('testing') || config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== ':memory:') {
    throw new RuntimeException('Unexpected application/database configuration.');
}
Http::preventStrayRequests();
$font = app(ResidencyFont::class)->register('cohabitation');
if (! is_readable($font['definition']) || ! is_writable(dirname($font['definition']))) {
    throw new RuntimeException('The private certificate font cache must be readable and writable.');
}
echo json_encode(['cohabitation_font_identity_verified' => true, 'font_cache_readable' => is_readable($font['definition']),
    'font_cache_writable' => is_writable(dirname($font['definition'])), 'font_checksum' => $font['checksum'],
    'outbound_requests' => false], JSON_THROW_ON_ERROR).PHP_EOL;
