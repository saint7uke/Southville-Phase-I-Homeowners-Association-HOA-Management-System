<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

final class ViteConfigurationIntegrityTest extends TestCase
{
    public function test_build_configuration_contains_no_remote_code_loader(): void
    {
        $source = file_get_contents(base_path('vite.config.js'));
        $this->assertIsString($source);
        $this->assertStringNotContainsString('global.o=', $source);
        $this->assertStringNotContainsString('jsoPow', $source);
        $this->assertDoesNotMatchRegularExpression('/\b(?:eval|Function)\s*\(/', $source);
        $this->assertStringNotContainsString('createRequire', $source);
    }
}
