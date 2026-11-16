<?php

return [
    // Explicit overrides are authoritative, even when invalid: never mask a setup error.
    'residency_font' => (static function (): ?string {
        $override = env('CERTIFICATE_RESIDENCY_FONT');
        if (is_string($override) && trim($override) !== '') {
            $path = str_replace('\\', '/', trim($override));

            return preg_match('~^(?:[A-Za-z]:/|/)~', $path) === 1 ? $path : base_path($path);
        }

        $private = storage_path('app/private/fonts/timesbd.ttf');
        if (file_exists($private)) {
            return $private;
        }

        return PHP_OS_FAMILY === 'Windows' ? 'C:/Windows/Fonts/timesbd.ttf' : null;
    })(),
    'butaw_font' => env('CERTIFICATE_BUTAW_FONT', PHP_OS_FAMILY === 'Windows' ? 'C:/Windows/Fonts/calibri.ttf' : null),
    'butaw_source_directory' => resource_path('certificates/resibo-butaw-pdf-2026'),
    'cohabitation_font' => env('CERTIFICATE_COHABITATION_FONT', PHP_OS_FAMILY === 'Windows' ? 'C:/Windows/Fonts/arial.ttf' : null),
    'cohabitation_source_directory' => resource_path('certificates/cohabitation-2026'),
];
