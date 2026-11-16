<?php

// This router belongs only to the disposable browser runner. It is not a public route.
declare(strict_types=1);
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if ($path !== '/' && is_file(__DIR__.'/../../public'.$path) && ! str_ends_with($path, '.php')) {
    return false;
}

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (! $app->environment('testing') || getenv('HOA_SMS_BROWSER_FIXTURES') !== 'true'
    || config('database.default') !== 'sqlite'
    || ! str_contains((string) config('database.connections.sqlite.database'), 'hoa-alert-browser-sms-')) {
    http_response_code(403);
    exit;
}
config(['textbee.enabled' => true, 'textbee.api_key' => 'synthetic-browser-key', 'textbee.test_mode' => true,
    'textbee.test_numbers' => ['+639171234567'], 'textbee.connection' => 'sync']);
Http::preventStrayRequests();
Http::fake(function ($request) {
    if (! str_starts_with($request->url(), 'https://api.textbee.dev/api/v1/gateway/send-')) {
        return Http::response(['data' => []]);
    }
    $payload = $request->data();
    if (preg_match('/\b([0-9]{6})\b/', $payload['message'] ?? '', $match)) {
        DB::table('sms_sync_states')->updateOrInsert(['key' => 'synthetic-browser-otp'], ['value' => $match[1]]);
    }

    return Http::response(['data' => ['success' => true,
        'smsBatchId' => 'browser-'.Str::uuid(), 'recipientCount' => count($payload['messages'] ?? $payload['recipients'] ?? [])]]);
});
$app->handleRequest(Request::capture());
