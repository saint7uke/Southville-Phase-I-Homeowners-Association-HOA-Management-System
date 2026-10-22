<?php

declare(strict_types=1);

// Source-only inventory: never boots Laravel, queries records or rewrites files.
$root = dirname(__DIR__);
$english = json_decode(file_get_contents($root.'/lang/en.json'), true, flags: JSON_THROW_ON_ERROR);
$filipino = json_decode(file_get_contents($root.'/lang/fil.json'), true, flags: JSON_THROW_ON_ERROR);
$findings = [];
$patterns = [
    'unwrapped_filament_copy' => '/->(?:label|helperText|modalHeading|modalDescription|title|body|placeholder|description)\(\s*\x27([^\x27\r\n]+)\x27\s*\)/u',
    'unwrapped_blade_text' => '/>\s*([A-Z][^<>{}\r\n]{3,})\s*</u',
    'translation_call' => '/(?:__|\bt)\(\s*([\x27\x22])([^\r\n]*?)\1\s*[,)]/u',
    'unwrapped_validation_message' => '/(?:withMessages\(\[\s*\x27[^\x27]+\x27\s*=>\s*|->errors\(\)->add\(\s*\x27[^\x27]+\x27\s*,\s*)\x27((?:\\\\\x27|[^\x27\r\n])+)\x27/u',
];
foreach (['app', 'resources'] as $directory) {
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/'.$directory, FilesystemIterator::SKIP_DOTS));
    foreach ($iterator as $file) {
        if (! preg_match('/\.(php|js|jsx)$/', $file->getFilename())) {
            continue;
        }
        $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
        $source = file_get_contents($file->getPathname());
        foreach ($patterns as $kind => $pattern) {
            if ($kind === 'unwrapped_filament_copy' && ! str_starts_with($relative, 'app/Filament/')) {
                continue;
            }
            if ($kind === 'unwrapped_blade_text' && ! str_ends_with($relative, '.blade.php')) {
                continue;
            }
            preg_match_all($pattern, $source, $matches, PREG_OFFSET_CAPTURE | PREG_SET_ORDER);
            foreach ($matches as $match) {
                $text = trim($match[$kind === 'translation_call' ? 2 : 1][0]);
                $text = str_replace("\\'", "'", $text);
                // Interpolated strings require manual placeholder review, not a literal-key claim.
                if (str_contains($text, '$') || ($kind === 'translation_call' && isset($filipino[$text]))) {
                    continue;
                }
                $findings[] = ['kind' => $kind, 'file' => $relative, 'line' => substr_count(substr($source, 0, $match[0][1]), "\n") + 1, 'text' => $text];
            }
        }
    }
}
$parity = array_merge(array_diff(array_keys($english), array_keys($filipino)), array_diff(array_keys($filipino), array_keys($english)));
echo json_encode([
    'dictionary_keys' => count($english),
    'parity_errors' => array_values($parity),
    'candidate_count' => count($findings),
    'limitations' => 'Candidates require review. Dynamic messages, default labels, interpolated text, content arrays and notification composition also need manual inspection. This inventory is not proof of complete localization.',
    'findings' => $findings,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES).PHP_EOL;
exit($parity !== [] || (in_array('--strict', $argv, true) && $findings !== []) ? 1 : 0);
