<?php

// Test-only PHP development-server router. Never register this as a production route.
declare(strict_types=1);

$testingRoot = realpath(__DIR__.'/../../storage/framework/testing');
$runtime = realpath((string) getenv('LARAVEL_STORAGE_PATH'));
$database = realpath((string) getenv('DB_DATABASE'));
if (PHP_SAPI !== 'cli-server' || getenv('APP_ENV') !== 'testing' || getenv('DB_CONNECTION') !== 'sqlite'
    || ! $testingRoot || ! $runtime || ! $database
    || ! str_starts_with($runtime, $testingRoot.DIRECTORY_SEPARATOR)
    || ! str_starts_with($database, $testingRoot.DIRECTORY_SEPARATOR)
    || ! str_contains(basename($database), 'phase23-browser-test-shared-receipts')) {
    http_response_code(404);
    exit;
}
// Initialize these before Application::configure(), including on Windows cli-server SAPIs.
$_ENV['LARAVEL_STORAGE_PATH'] = $_SERVER['LARAVEL_STORAGE_PATH'] = $runtime;
$_ENV['VIEW_COMPILED_PATH'] = $_SERVER['VIEW_COMPILED_PATH'] = $runtime.'/framework/views';

// Propagate `false` for existing public assets so PHP serves JavaScript/CSS normally.
return require __DIR__.'/../../vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php';
