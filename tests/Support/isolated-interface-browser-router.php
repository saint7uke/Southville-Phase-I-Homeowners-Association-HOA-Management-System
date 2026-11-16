<?php

declare(strict_types=1);

// Test harness only: initialize storage before Application::configure() on Windows.
$runtime = realpath((string) getenv('LARAVEL_STORAGE_PATH'));
$database = realpath((string) getenv('DB_DATABASE'));
$temporaryRoot = realpath(sys_get_temp_dir());
if (PHP_SAPI !== 'cli-server' || getenv('APP_ENV') !== 'local' || getenv('DB_CONNECTION') !== 'sqlite'
    || ! $runtime || ! $database || ! $temporaryRoot
    || dirname($runtime) !== dirname($database)
    || ! str_starts_with(dirname($runtime), $temporaryRoot.DIRECTORY_SEPARATOR)
    || ! str_starts_with(basename(dirname($runtime)), 'hoa-alert-browser-interface-')) {
    http_response_code(404);
    exit;
}
$_ENV['LARAVEL_STORAGE_PATH'] = $_SERVER['LARAVEL_STORAGE_PATH'] = $runtime;
$_ENV['VIEW_COMPILED_PATH'] = $_SERVER['VIEW_COMPILED_PATH'] = $runtime.'/framework/views';

// cli-server may reset the startup timeout between Windows requests. This is
// confined to the guarded disposable payment harness, never public/index.php.
if (getenv('HOA_PAYMENT_BROWSER') === 'true') {
    set_time_limit(180);
}

return require __DIR__.'/../../vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php';
