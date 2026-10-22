<?php

return [
    // Supply an appropriately licensed Times New Roman Bold font on non-Windows hosts.
    'residency_font' => env('CERTIFICATE_RESIDENCY_FONT', PHP_OS_FAMILY === 'Windows' ? 'C:/Windows/Fonts/timesbd.ttf' : null),
    'butaw_font' => env('CERTIFICATE_BUTAW_FONT', PHP_OS_FAMILY === 'Windows' ? 'C:/Windows/Fonts/calibri.ttf' : null),
    'butaw_source_directory' => resource_path('certificates/resibo-butaw-pdf-2026'),
    'cohabitation_font' => env('CERTIFICATE_COHABITATION_FONT', PHP_OS_FAMILY === 'Windows' ? 'C:/Windows/Fonts/arial.ttf' : null),
    'cohabitation_source_directory' => resource_path('certificates/cohabitation-2026'),
];
