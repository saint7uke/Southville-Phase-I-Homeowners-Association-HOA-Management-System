<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

final class ApplicationConfigurationTest extends TestCase
{
    public function test_the_test_environment_uses_an_explicitly_isolated_database(): void
    {
        $this->assertSame('testing', app()->environment());

        $connection = (string) config('database.default');
        $database = (string) config("database.connections.{$connection}.database");

        if ($connection === 'sqlite') {
            $this->assertSame(':memory:', $database);

            return;
        }

        $this->assertTrue((bool) filter_var(
            getenv('HOA_ALLOW_DESTRUCTIVE_TEST_DATABASE') ?: false,
            FILTER_VALIDATE_BOOL,
        ));
        $this->assertMatchesRegularExpression('/(?:^|[_-])tests?(?:$|[_-])/i', $database);
    }

    public function test_the_application_uses_the_community_timezone(): void
    {
        $this->assertSame('Asia/Manila', config('app.timezone'));
        $this->assertSame('Asia/Manila', now()->timezoneName);
    }

    public function test_the_health_endpoint_confirms_database_readiness(): void
    {
        $this->get('/up')->assertOk();
    }

    public function test_the_health_endpoint_fails_when_the_database_is_unavailable(): void
    {
        DB::shouldReceive('select')
            ->once()
            ->with('select 1')
            ->andThrow(new RuntimeException('Simulated database outage.'));

        $this->get('/up')->assertStatus(500);
    }
}
