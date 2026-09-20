<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\AdminUserSeeder;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Tests\TestCase;

final class AdminUserSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_one_normalized_active_admin_without_rotating_it_on_repeat(): void
    {
        $this->configureAdmin();

        $this->seed(AdminUserSeeder::class);

        $admin = User::query()->where('email', 'initial.admin@example.test')->firstOrFail();
        $originalPassword = $admin->password;
        $this->assertSame('María-Jane', $admin->first_name);
        $this->assertSame("O'Connor", $admin->last_name);
        $this->assertSame('Active', $admin->account_status);
        $this->assertNotNull($admin->email_verified_at);
        $this->assertTrue($admin->hasRole('hoa_admin'));
        $this->assertTrue(Hash::check('SecureInitialAdmin!2026', $admin->password));

        config()->set('hoa.initial_admin.password', 'DifferentSecureAdmin!2026');
        config()->set('hoa.initial_admin.password_confirmation', 'DifferentSecureAdmin!2026');
        $this->seed(AdminUserSeeder::class);

        $this->assertSame(1, User::query()->where('email', 'initial.admin@example.test')->count());
        $this->assertSame($originalPassword, $admin->refresh()->password);
    }

    public function test_it_refuses_missing_configuration_and_existing_account_elevation(): void
    {
        config()->set('hoa.initial_admin', []);

        try {
            $this->seed(AdminUserSeeder::class);
            $this->fail('Missing initial Admin configuration must be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('email', $exception->errors());
            $this->assertArrayHasKey('password', $exception->errors());
        }

        $this->configureAdmin();
        User::factory()->create(['email' => 'initial.admin@example.test', 'account_status' => 'Active']);

        $this->expectException(RuntimeException::class);
        $this->seed(AdminUserSeeder::class);
    }

    public function test_the_demonstration_seeder_refuses_production(): void
    {
        app()->detectEnvironment(static fn (): string => 'production');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('demonstration identities');

        app(DatabaseSeeder::class)->run();
    }

    private function configureAdmin(): void
    {
        config()->set('hoa.initial_admin', [
            'first_name' => '  maría-jane ',
            'middle_name' => null,
            'last_name' => " o'connor ",
            'suffix' => null,
            'sex' => 'Female',
            'contact_number' => '09171234567',
            'date_of_birth' => '1984-05-16',
            'email' => ' INITIAL.ADMIN@EXAMPLE.TEST ',
            'password' => 'SecureInitialAdmin!2026',
            'password_confirmation' => 'SecureInitialAdmin!2026',
        ]);
    }
}
