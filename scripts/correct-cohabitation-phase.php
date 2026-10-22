<?php

declare(strict_types=1);

// Authorized, copy-only phase/signatory corrections. No database access or source approval.
$directory = dirname(__DIR__).'/resources/certificates/cohabitation-2026';
$source = $directory.'/original-unadjusted.docx';
$destination = $argv[1] ?? $directory.'/original.docx';
$expected = '9b572c18fc08ecc0edc909dced3069b3ead51f29c1be6beb6737cc0a3a0fe436';
if (! is_file($source) || ! hash_equals($expected, hash_file('sha256', $source)) || file_exists($destination)) {
    throw new RuntimeException('Expected preserved upload missing/changed, or corrected copy already exists. Refusing overwrite.');
}
$original = new ZipArchive;
if ($original->open($source) !== true) {
    throw new RuntimeException('The preserved DOCX could not be read.');
}
$before = $original->getFromName('word/document.xml');
$after = str_replace('Southville 2 Phase 1-A </w:t>', 'Southville 2 Phase 1 - </w:t>', $before, $count);
if ($count !== 1) {
    $original->close();
    throw new RuntimeException('Expected exactly one authorized phase phrase. No correction made.');
}
$after = str_replace('<w:t>D,</w:t>', '<w:t>D.</w:t>', $after, $signatoryCount);
if ($signatoryCount !== 1) {
    $original->close();
    throw new RuntimeException('Expected exactly one authorized signatory-initial correction. No correction made.');
}
if (! copy($source, $destination)) {
    $original->close();
    throw new RuntimeException('Could not create the separate corrected DOCX.');
}
$corrected = new ZipArchive;
try {
    if ($corrected->open($destination) !== true || ! $corrected->addFromString('word/document.xml', $after) || ! $corrected->close()) {
        throw new RuntimeException('Could not write the authorized correction.');
    }
    if ($corrected->open($destination) !== true || $corrected->numFiles !== $original->numFiles) {
        throw new RuntimeException('DOCX package entries changed unexpectedly.');
    }
    for ($index = 0; $index < $original->numFiles; $index++) {
        $name = $original->getNameIndex($index);
        $expectedBytes = $name === 'word/document.xml' ? $after : $original->getFromIndex($index);
        if ($corrected->getFromName($name) !== $expectedBytes) {
            throw new RuntimeException('Unrelated DOCX package content changed: '.$name);
        }
    }
    if (! hash_equals($expected, hash_file('sha256', $source))) {
        throw new RuntimeException('The preserved original changed unexpectedly.');
    }
    echo json_encode(['original_sha256' => $expected, 'corrected_sha256' => hash_file('sha256', $destination),
        'changed_part' => 'word/document.xml', 'phase_replacement_count' => $count, 'signatory_replacement_count' => $signatoryCount,
        'unchanged_other_parts' => $original->numFiles - 1], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR).PHP_EOL;
} catch (Throwable $exception) {
    try {
        $corrected->close();
    } catch (ValueError) {
        // A failed open/close may already have released the new output archive.
    }
    unlink($destination); // Only the newly created, explicit output; never the upload.
    throw $exception;
} finally {
    try {
        $corrected->close();
    } catch (ValueError) {
        // Already closed during failure cleanup.
    }
    $original->close();
}
