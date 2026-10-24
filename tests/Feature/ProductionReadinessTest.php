<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

final class ProductionReadinessTest extends TestCase
{
    public function test_local_configuration_fails_closed_without_operational_attestations(): void
    {
        $this->assertSame(1, Artisan::call('hoa:release-check', ['--json' => true]));
        $output = Artisan::output();
        $this->assertStringContainsString('"ready": false', $output);
        $this->assertStringContainsString('"security_incident_closed": false', $output);
    }

    public function test_complete_production_configuration_passes_the_gate(): void
    {
        app()->detectEnvironment(fn (): string => 'production');
        config()->set([
            'app.debug' => false,
            'app.url' => 'https://hoa.example.test',
            'app.key' => 'base64:'.base64_encode(str_repeat('x', 32)),
            'session.secure' => true,
            'session.http_only' => true,
            'session.same_site' => 'lax',
            'queue.default' => 'database',
            'mail.default' => 'smtp',
            'filesystems.default' => 'local',
            'database.default' => 'mysql',
            'hoa.release.queue_worker_verified' => true,
            'hoa.release.scheduler_verified' => true,
            'hoa.release.backup_restore_verified' => true,
            'hoa.release.mail_delivery_verified' => true,
            'hoa.release.private_storage_verified' => true,
            'hoa.release.security_incident_closed' => true,
        ]);

        $this->assertSame(0, Artisan::call('hoa:release-check', ['--json' => true]));
        $this->assertStringContainsString('"ready": true', Artisan::output());
    }
}
