<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Validation\ValidationException;
use TCPDF_FONTS;

final class ResidencyFont
{
    public function checksum(string $profile = 'residency'): string
    {
        $path = $this->path($profile);
        if (! is_string($path) || ! is_file($path) || ! is_readable($path)) {
            if ($profile === 'cohabitation') {
                throw ValidationException::withMessages(['type' => __('Install the licensed Arial font before generating Cohabitation certificates.')]);
            }
            throw ValidationException::withMessages(['type' => __($profile === 'butaw' ? 'Install the licensed Calibri font before generating Resibo Butaw receipts.' : 'Install the licensed Times New Roman Bold font before generating Residency certificates.')]);
        }

        return hash_file('sha256', $path);
    }

    public function register(string $profile = 'residency'): array
    {
        $checksum = $this->checksum($profile);
        $directory = storage_path('framework/cache/certificate-fonts/'.$checksum.'/');
        if (! is_dir($directory) && ! mkdir($directory, 0700, true) && ! is_dir($directory)) {
            throw ValidationException::withMessages(['type' => __('The certificate font cache could not be created.')]);
        }
        // Serialize font conversion across PHP workers. Never write to vendor or public storage.
        $lock = fopen($directory.'conversion.lock', 'c');
        if ($lock === false || ! flock($lock, LOCK_EX)) {
            throw ValidationException::withMessages(['type' => __('The certificate font cache could not be created.')]);
        }
        try {
            $name = TCPDF_FONTS::addTTFfont($this->path($profile), 'TrueTypeUnicode', '', 32, $directory);
            if (! is_string($name) || ! is_file($directory.$name.'.php')) {
                throw ValidationException::withMessages(['type' => __('The required certificate font could not be loaded.')]);
            }
            // TCPDF's generated definition contains the source font's character-width map.
            $metadata = (static function (string $definition): array {
                include $definition;

                return ['family' => $name ?? '', 'characters' => $cw ?? []];
            })($directory.$name.'.php');
            if ($profile === 'cohabitation' && ! in_array($metadata['family'], ['Arial', 'ArialMT'], true)) {
                throw ValidationException::withMessages(['type' => __('The configured Cohabitation font must be Arial, not a substitute.')]);
            }
            if ($profile !== 'cohabitation' && ($profile === 'butaw' ? strcasecmp($metadata['family'], 'Calibri') !== 0 : (stripos($metadata['family'], 'TimesNewRoman') === false || stripos($metadata['family'], 'Bold') === false))) {
                throw ValidationException::withMessages(['type' => __($profile === 'butaw' ? 'The configured receipt font must be Calibri, not a substitute.' : 'The configured font must be Times New Roman Bold, not a substitute.')]);
            }

            return ['name' => $name, 'definition' => $directory.$name.'.php', 'characters' => $metadata['characters'], 'checksum' => $checksum];
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    private function path(string $profile): mixed
    {
        if (! in_array($profile, ['residency', 'butaw', 'cohabitation'], true)) {
            throw ValidationException::withMessages(['type' => __('Unsupported original-document font.')]);
        }

        return config('certificates.'.$profile.'_font');
    }
}
