<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

final class PhaseTwoAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_canonical_login_selects_exactly_one_guard_with_role_precedence(): void
    {
        foreach (['homeowner' => 'homeowner', 'hoa_staff' => 'staff', 'hoa_admin' => 'admin'] as $role => $guard) {
            $user = User::factory()->create(['password' => Hash::make('StrongPassword!2026')]);
            $user->assignRole($role);
            $this->post('/login', ['email' => strtoupper($user->email), 'password' => 'StrongPassword!2026'])->assertRedirect('/'.$guard);
            $this->assertAuthenticatedAs($user, $guard);
            foreach (['web', 'admin', 'staff', 'homeowner'] as $other) {
                if ($other !== $guard) {
                    $this->assertGuest($other);
                }
            }
        }
    }

    public function test_inactive_accounts_cannot_authenticate(): void
    {
        $user = User::factory()->create(['account_status' => 'Suspended']);
        $user->assignRole('hoa_admin');
        $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertSessionHasErrors('email');
        $this->assertGuest('admin');
    }

    public function test_multirole_precedence_and_legacy_post_do_not_get_intercepted_by_redirects(): void
    {
        $user = User::factory()->create();
        $user->assignRole(['homeowner', 'hoa_staff', 'hoa_admin']);
        $this->post('/homeowner/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect('/admin');
        $this->assertAuthenticatedAs($user, 'admin');
        $this->assertGuest('homeowner');
        $this->assertGuest('staff');
    }

    public function test_setup_tokens_expire_are_single_use_and_do_not_approve_pending_accounts(): void
    {
        $user = User::factory()->create(['account_status' => 'Pending']);
        $user->assignRole('hoa_staff');
        $token = Password::broker()->createToken($user);
        $data = ['email' => $user->email, 'token' => $token, 'password' => 'FreshSecurePassword!2026', 'password_confirmation' => 'FreshSecurePassword!2026'];
        $this->post('/homeowner/reset-password', $data)->assertRedirect('/login');
        $this->assertSame('Pending', $user->fresh()->account_status);
        $this->post('/homeowner/reset-password', $data)->assertSessionHasErrors('email');
        $token = Password::broker()->createToken($user);
        $this->travel(61)->minutes();
        $this->post('/homeowner/reset-password', [...$data, 'token' => $token])->assertSessionHasErrors('email');
    }

    public function test_legacy_login_pages_redirect_to_the_canonical_form(): void
    {
        foreach (['/homeowner/login', '/admin/login', '/staff/login'] as $url) {
            $this->get($url)->assertRedirect('/login');
        }
        $this->get('/login')->assertOk()->assertSee('Sign in');
    }
}
