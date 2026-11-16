<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\ResidencyFont;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class ResidencyFontConfigurationTest extends TestCase
{
    private string $directory;

    private string $originalStorage;

    private array $originalEnvironment;

    protected function setUp(): void
    {
        parent::setUp();
        $this->originalStorage = storage_path();
        $this->directory = $this->originalStorage.'/framework/testing/residency-font-'.bin2hex(random_bytes(8));
        File::ensureDirectoryExists($this->directory, 0700);
        $this->app->useStoragePath($this->directory);
        $key = 'CERTIFICATE_RESIDENCY_FONT';
        $this->originalEnvironment = [$_ENV[$key] ?? null, $_SERVER[$key] ?? null, getenv($key)];
        $this->override(null);
    }

    protected function tearDown(): void
    {
        $key = 'CERTIFICATE_RESIDENCY_FONT';
        [$environment, $server, $process] = $this->originalEnvironment;
        unset($_ENV[$key], $_SERVER[$key]);
        if ($environment !== null) {
            $_ENV[$key] = $environment;
        }
        if ($server !== null) {
            $_SERVER[$key] = $server;
        }
        putenv($process === false ? $key : $key.'='.$process);
        $this->app->useStoragePath($this->originalStorage);
        File::deleteDirectory($this->directory);
        parent::tearDown();
    }

    public function test_private_file_is_preferred_and_blank_override_uses_the_same_default(): void
    {
        $path = storage_path('app/private/fonts/timesbd.ttf');
        File::ensureDirectoryExists(dirname($path));
        File::put($path, 'presence-only configuration fixture');
        $this->assertSame($path, $this->reload());
        $this->override('  ');
        $this->assertSame($path, $this->reload());
    }

    public function test_missing_private_file_preserves_only_the_platform_fallback(): void
    {
        $this->assertSame(PHP_OS_FAMILY === 'Windows' ? 'C:/Windows/Fonts/timesbd.ttf' : null, $this->reload());
    }

    #[DataProvider('overrides')]
    public function test_explicit_paths_are_authoritative_and_relative_paths_use_the_project_root(string $override, string $expected): void
    {
        $this->override($override);
        $this->assertSame(str_starts_with($expected, 'project:') ? base_path(substr($expected, 8)) : $expected, $this->reload());
    }

    public static function overrides(): array
    {
        return [
            'relative' => ['storage/app/private/fonts/timesbd.ttf', 'project:storage/app/private/fonts/timesbd.ttf'],
            'relative Windows separators' => ['storage\\app\\private\\fonts\\timesbd.ttf', 'project:storage/app/private/fonts/timesbd.ttf'],
            'Linux absolute' => ['/private/fonts/timesbd.ttf', '/private/fonts/timesbd.ttf'],
            'Windows absolute' => ['C:\\private\\fonts\\timesbd.ttf', 'C:/private/fonts/timesbd.ttf'],
            'UNC absolute' => ['\\\\host\\private\\timesbd.ttf', '//host/private/timesbd.ttf'],
        ];
    }

    public function test_invalid_override_does_not_fall_back_to_an_existing_private_font(): void
    {
        $this->installTestFont();
        $this->override($this->directory.'/missing.ttf');
        $this->assertSame(str_replace('\\', '/', $this->directory.'/missing.ttf'), $this->reload());
        $this->expectException(ValidationException::class);
        app(ResidencyFont::class)->register();
    }

    public function test_downloaded_bold_font_registers_in_cold_and_warm_private_caches(): void
    {
        $path = $this->installTestFont();
        $this->reload();
        $cold = app(ResidencyFont::class)->register();
        $warm = app(ResidencyFont::class)->register();
        $this->assertSame($cold, $warm);
        $this->assertSame('1c24128bee049c13004fe01e907b9dae2be541d6491d3a993facdba8da2c02b5', $cold['checksum']);
        $this->assertSame(hash_file('sha256', $path), $cold['checksum']);
        $this->assertTrue(is_readable($path));
        $this->assertTrue(is_writable(dirname($cold['definition'])));
        $this->assertStringStartsWith(storage_path('framework/cache/certificate-fonts/'), $cold['definition']);
        $this->assertArrayHasKey(mb_ord('é'), $cold['characters']);
        $family = (static function (string $definition): string {
            include $definition;

            return $name;
        })($cold['definition']);
        $this->assertSame('TimesNewRomanPS-BoldMT', $family);
    }

    #[DataProvider('validOverrideModes')]
    public function test_an_explicit_override_can_load_the_actual_downloaded_font(bool $absolute): void
    {
        $relative = 'Times New Roman Bold/Times New Roman Bold.ttf';
        if (! is_readable(base_path($relative))) {
            $this->markTestSkipped('Requires the locally supplied licensed bold font; no substitute is used.');
        }
        $this->override($absolute ? base_path($relative) : $relative);
        $this->reload();
        $this->assertSame(hash_file('sha256', base_path($relative)), app(ResidencyFont::class)->checksum());
    }

    public static function validOverrideModes(): array
    {
        return ['relative' => [false], 'absolute' => [true]];
    }

    public function test_regular_face_is_rejected_even_when_renamed_as_bold(): void
    {
        $regular = base_path('Times New Roman Bold/times.ttf');
        if (! is_file($regular)) {
            $this->markTestSkipped('Requires the locally supplied regular-face negative fixture; no proprietary font is distributed.');
        }
        $path = storage_path('app/private/fonts/timesbd.ttf');
        File::ensureDirectoryExists(dirname($path));
        File::copy($regular, $path);
        $this->reload();
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('The configured font must be Times New Roman Bold, not a substitute.');
        app(ResidencyFont::class)->register();
    }

    public function test_damaged_font_is_rejected(): void
    {
        $path = storage_path('app/private/fonts/timesbd.ttf');
        File::ensureDirectoryExists(dirname($path));
        File::put($path, str_repeat('not-a-font', 20));
        $this->reload();
        $this->expectException(ValidationException::class);
        app(ResidencyFont::class)->register();
    }

    public function test_unreadable_override_is_rejected_on_permission_enforcing_platforms(): void
    {
        $path = $this->installTestFont();
        chmod($path, 0000);
        if (is_readable($path)) {
            chmod($path, 0600);
            $this->markTestSkipped('This platform does not enforce POSIX read permissions through chmod.');
        }
        try {
            $this->override($path);
            $this->reload();
            $this->expectException(ValidationException::class);
            app(ResidencyFont::class)->register();
        } finally {
            chmod($path, 0600);
        }
    }

    private function installTestFont(): string
    {
        $source = base_path('Times New Roman Bold/Times New Roman Bold.ttf');
        if (! is_readable($source)) {
            $this->markTestSkipped('Requires the locally supplied licensed bold font; no substitute is used.');
        }
        $path = storage_path('app/private/fonts/timesbd.ttf');
        File::ensureDirectoryExists(dirname($path));
        File::copy($source, $path);

        return $path;
    }

    private function override(?string $value): void
    {
        $key = 'CERTIFICATE_RESIDENCY_FONT';
        if ($value === null) {
            unset($_ENV[$key], $_SERVER[$key]);
            putenv($key);
        } else {
            $_ENV[$key] = $_SERVER[$key] = $value;
            putenv($key.'='.$value);
        }
    }

    private function reload(): ?string
    {
        $configuration = require base_path('config/certificates.php');
        config(['certificates.residency_font' => $configuration['residency_font']]);

        return $configuration['residency_font'];
    }
}
