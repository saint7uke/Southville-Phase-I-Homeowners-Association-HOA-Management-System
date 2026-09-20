<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Homeowner;
use App\Models\User;
use Database\Seeders\UatSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

final class UatSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_prepares_idempotent_non_production_role_and_ownership_fixtures(): void
    {
        $this->seed(UatSeeder::class);
        $this->seed(UatSeeder::class);

        $this->assertTrue(User::query()->where('email', 'staff2@southville.test')->firstOrFail()->hasRole('hoa_staff'));

        $secondHomeowner = User::query()->where('email', 'resident2@southville.test')->firstOrFail();
        $this->assertTrue($secondHomeowner->hasRole('homeowner'));
        $this->assertSame('Active', $secondHomeowner->account_status);
        $this->assertSame('7', $secondHomeowner->homeowner?->block);
        $this->assertSame('9', $secondHomeowner->homeowner?->lot);

        $pending = User::query()->where('email', 'pending@southville.test')->firstOrFail();
        $this->assertSame('Pending', $pending->account_status);
        $this->assertNull($pending->email_verified_at);
        $this->assertSame('Inactive', $pending->homeowner?->status);

        $this->assertSame(6, User::query()->count());
        $this->assertSame(3, Homeowner::query()->count());
    }

    public function test_it_refuses_to_seed_uat_accounts_in_production(): void
    {
        config()->set('app.env', 'production');

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('UAT fixtures cannot be seeded in production.');

        $this->app->make(UatSeeder::class)->run();
    }
}
