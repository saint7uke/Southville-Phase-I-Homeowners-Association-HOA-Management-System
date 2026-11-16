<?php

return [
    'enabled' => (bool) env('TEXTBEE_ENABLED', false),
    'test_mode' => (bool) env('TEXTBEE_TEST_MODE', true),
    'test_numbers' => array_values(array_filter(array_map('trim', explode(',', (string) env('TEXTBEE_TEST_NUMBERS', ''))))),
    'api_key' => env('TEXTBEE_API_KEY'),
    'device_id' => env('TEXTBEE_DEVICE_ID', '6af13a260aecd9e3040dc00d'),
    'base_url' => 'https://api.textbee.dev/api/v1/gateway',
    'batch_size' => 20,
    'connection' => 'database',
    'retention_days' => 90,
];
