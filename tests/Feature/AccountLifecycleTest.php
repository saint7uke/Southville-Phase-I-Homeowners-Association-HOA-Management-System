<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Users\ChangeAccountStatus;
use App\Actions\Users\CreatePendingUser;
use App\Enums\UserAccountStatus;
use App\Filament\Auth\Pages\RequestPasswordReset;
use App\Models\Homeowner;
use App\Models\User;
use App\Notifications\HomeownerAccountStatusChanged;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Auth\Notifications\ResetPassword;
use Filament\Auth\Notifications\VerifyEmail;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Database\Eloquent\MassAssignmentException;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class AccountLifecycleTest extends TestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_admin_activates_pending_homeowner_and_notification_waits_for_commit(): void
    {
        Notification::fake();
        $admin = $this->userWithRole('hoa_admin');
        [$resident, $homeowner] = $this->homeowner(UserAccountStatus::Pending);

        DB::beginTransaction();
        app(ChangeAccountStatus::class)->handle($resident, UserAccountStatus::Active, $admin);

        Notification::assertNothingSent();
        DB::commit();

        $resident->refresh();
        $this->assertSame(UserAccountStatus::Active->value, $resident->account_status);
        $this->assertSame($admin->id, $resident->approved_by);
        $this->assertNotNull($resident->approved_at);
        $this->assertSame('Active', $homeowner->refresh()->status);
        Notification::assertSentTo($resident, HomeownerAccountStatusChanged::class);
        Notification::assertSentTo($resident, VerifyEmail::class);
    }

    public function test_suspension_requires_reason_and_reactivation_clears_suspension_fields(): void
    {
        Notification::fake();
        $admin = $this->userWithRole('hoa_admin');
        [$resident, $homeowner] = $this->homeowner(UserAccountStatus::Active);

        try {
            app(ChangeAccountStatus::class)->handle($resident, UserAccountStatus::Suspended, $admin);
            $this->fail('Suspension without a reason should fail.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('suspension_reason', $exception->errors());
        }

        app(ChangeAccountStatus::class)->handle($resident, UserAccountStatus::Suspended, $admin, 'Repeated gate access violations.');
        $resident->refresh();
        $this->assertSame(UserAccountStatus::Suspended->value, $resident->account_status);
        $this->assertSame($admin->id, $resident->suspended_by);
        $this->assertNotNull($resident->suspended_at);
        $this->assertSame('Inactive', $homeowner->refresh()->status);

        app(ChangeAccountStatus::class)->handle($resident, UserAccountStatus::Active, $admin);
        $resident->refresh();
        $this->assertNull($resident->suspended_by);
        $this->assertNull($resident->suspended_at);
        $this->assertNull($resident->suspension_reason);
        $this->assertSame('Active', $homeowner->refresh()->status);
    }

    public function test_invalid_transition_is_rejected(): void
    {
        $admin = $this->userWithRole('hoa_admin');
        [$resident] = $this->homeowner(UserAccountStatus::Pending);

        $this->expectException(ValidationException::class);
        app(ChangeAccountStatus::class)->handle($resident, UserAccountStatus::Suspended, $admin, 'Invalid transition.');
    }

    public function test_non_admin_actor_is_rejected(): void
    {
        $staff = $this->userWithRole('hoa_staff');
        [$resident] = $this->homeowner(UserAccountStatus::Pending);

        try {
            app(ChangeAccountStatus::class)->handle($resident, UserAccountStatus::Active, $staff);
            $this->fail('Staff must not change account lifecycle state.');
        } catch (AuthorizationException) {
            $this->assertSame(UserAccountStatus::Pending->value, $resident->refresh()->account_status);
        }
    }

    public function test_self_deactivation_and_last_active_admin_deactivation_are_blocked(): void
    {
        $admin = $this->userWithRole('hoa_admin');

        try {
            app(ChangeAccountStatus::class)->handle($admin, UserAccountStatus::Inactive, $admin);
            $this->fail('Self-deactivation should fail.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('account_status', $exception->errors());
        }

        $inactiveAdmin = $this->userWithRole('hoa_admin', ['account_status' => 'Inactive']);

        try {
            app(ChangeAccountStatus::class)->handle($admin, UserAccountStatus::Inactive, $inactiveAdmin);
            $this->fail('The final active administrator should be protected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('account_status', $exception->errors());
        }
    }

    public function test_suspension_preserves_delinquency_and_revokes_database_sessions(): void
    {
        Notification::fake();
        $admin = $this->userWithRole('hoa_admin');
        [$resident, $homeowner] = $this->homeowner(UserAccountStatus::Active);
        $homeowner->update(['status' => 'Delinquent']);
        $rememberToken = $resident->getRememberToken();
        DB::table('sessions')->insert([
            'id' => 'resident-session',
            'user_id' => $resident->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit',
            'payload' => 'test',
            'last_activity' => now()->timestamp,
        ]);

        app(ChangeAccountStatus::class)->handle($resident, UserAccountStatus::Suspended, $admin, 'Security review.');

        $this->assertSame('Delinquent', $homeowner->refresh()->status);
        $this->assertDatabaseMissing('sessions', ['user_id' => $resident->id]);
        $this->assertNotSame($rememberToken, $resident->refresh()->getRememberToken());
    }

    public function test_email_change_clears_existing_verification(): void
    {
        $resident = User::factory()->create(['email_verified_at' => now()]);

        $resident->update(['email' => 'changed@example.test']);

        $this->assertNull($resident->refresh()->email_verified_at);
    }

    public function test_user_creation_is_pending_role_whitelisted_and_lifecycle_fields_are_sealed(): void
    {
        $admin = $this->userWithRole('hoa_admin');
        $data = [
            'first_name' => 'Elena', 'middle_name' => null, 'last_name' => 'Santos', 'suffix' => null,
            'sex' => 'Female', 'contact_number' => '09175550123', 'date_of_birth' => '1992-06-10',
            'email' => 'elena@example.test', 'password' => 'SecureResident2026', 'role' => 'hoa_staff',
        ];

        $created = app(CreatePendingUser::class)->handle($data, $admin);

        $this->assertSame(UserAccountStatus::Pending->value, $created->account_status);
        $this->assertTrue($created->hasRole('hoa_staff'));

        try {
            app(CreatePendingUser::class)->handle([
                ...$data,
                'email' => 'crafted@example.test',
                'account_status' => 'Active',
            ], $admin);
            $this->fail('Crafted lifecycle state must be prohibited.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('account_status', $exception->errors());
        }

        $this->expectException(MassAssignmentException::class);
        User::query()->create([
            'first_name' => 'Crafted', 'last_name' => 'Account', 'sex' => 'Female',
            'contact_number' => '09175550999', 'date_of_birth' => '1990-01-01',
            'email' => 'mass-assignment@example.test', 'password' => 'SecureResident2026',
            'account_status' => 'Active',
        ]);
    }

    public function test_user_creation_requires_an_allowed_privileged_role(): void
    {
        $admin = $this->userWithRole('hoa_admin');

        $this->expectException(ValidationException::class);
        app(CreatePendingUser::class)->handle([
            'first_name' => 'Invalid', 'last_name' => 'Role', 'sex' => 'Male',
            'contact_number' => '09175550456', 'date_of_birth' => '1990-01-01',
            'email' => 'invalid-role@example.test', 'password' => 'SecureResident2026',
            'role' => 'homeowner',
        ], $admin);
    }

    /** @return array<string, array{UserAccountStatus}> */
    public static function portalStatusPageProvider(): array
    {
        return [
            'pending' => [UserAccountStatus::Pending],
            'rejected' => [UserAccountStatus::Rejected],
        ];
    }

    #[DataProvider('portalStatusPageProvider')]
    public function test_portal_preserves_pending_and_rejected_status_page(UserAccountStatus $status): void
    {
        [$resident] = $this->homeowner($status);

        $this->post('/portal/login', ['email' => $resident->email, 'password' => 'password'])
            ->assertRedirect('/portal/status');

        $this->assertAuthenticatedAs($resident, 'web');
        $this->assertSame('portal', $resident->refresh()->last_login_panel);
    }

    /** @return array<string, array{UserAccountStatus}> */
    public static function unavailablePortalStatusProvider(): array
    {
        return [
            'inactive' => [UserAccountStatus::Inactive],
            'suspended' => [UserAccountStatus::Suspended],
        ];
    }

    #[DataProvider('unavailablePortalStatusProvider')]
    public function test_inactive_and_suspended_homeowners_cannot_keep_portal_session(UserAccountStatus $status): void
    {
        [$resident] = $this->homeowner($status);

        $this->from('/portal/login')
            ->post('/portal/login', ['email' => $resident->email, 'password' => 'password'])
            ->assertRedirect('/portal/login')
            ->assertSessionHasErrors('email');

        $this->assertGuest('web');
        $this->assertNull($resident->refresh()->last_login_at);
        $this->assertDatabaseHas('audit_logs', ['user_id' => $resident->id, 'action' => 'Auth.failed']);
        $this->assertDatabaseMissing('audit_logs', ['user_id' => $resident->id, 'action' => 'Auth.login']);
    }

    public function test_wrong_role_credentials_emit_failed_and_never_login(): void
    {
        $staff = $this->userWithRole('hoa_staff');

        $this->post('/portal/login', ['email' => $staff->email, 'password' => 'password'])
            ->assertSessionHasErrors('email');

        $this->assertGuest('web');
        $this->assertDatabaseHas('audit_logs', ['user_id' => $staff->id, 'action' => 'Auth.failed']);
        $this->assertDatabaseMissing('audit_logs', ['user_id' => $staff->id, 'action' => 'Auth.login']);
    }

    /** @return array<string, array{string, string}> */
    public static function panelGuardProvider(): array
    {
        return [
            'admin' => ['admin', 'admin'],
            'staff' => ['staff', 'staff'],
            'homeowner' => ['homeowner', 'homeowner'],
        ];
    }

    #[DataProvider('panelGuardProvider')]
    public function test_login_event_records_the_specific_panel_guard(string $guard, string $panel): void
    {
        $user = User::factory()->create();
        $user->assignRole(match ($guard) {
            'admin' => 'hoa_admin',
            'staff' => 'hoa_staff',
            default => 'homeowner',
        });

        Auth::guard($guard)->login($user);

        $user->refresh();
        $this->assertSame($panel, $user->last_login_panel);
        $this->assertNotNull($user->last_login_at);
        $this->assertSame('127.0.0.1', $user->last_login_ip);
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $user->id,
            'action' => 'Auth.login',
            'new_values' => json_encode(['panel' => $panel]),
        ]);
    }

    public function test_all_panels_expose_password_reset_and_only_homeowner_requires_verification(): void
    {
        foreach (['admin', 'staff', 'homeowner'] as $panel) {
            $this->get("/{$panel}/password-reset/request")->assertOk();
            $configuredPanel = Filament::getPanel($panel);
            $this->assertSame('users', $configuredPanel->getAuthPasswordBroker());
            $this->assertSame(RequestPasswordReset::class, $configuredPanel->getRequestPasswordResetRouteAction());
        }

        $unverified = $this->userWithRole('homeowner', ['email_verified_at' => null]);
        $this->actingAs($unverified, 'homeowner')
            ->get('/homeowner')
            ->assertRedirect('/homeowner/email-verification/prompt');

        $verifiedAdmin = $this->userWithRole('hoa_admin', ['email_verified_at' => null]);
        $this->actingAs($verifiedAdmin, 'admin')->get('/admin')->assertOk();
    }

    public function test_password_reset_feedback_is_generic_for_unknown_and_eligible_accounts(): void
    {
        Notification::fake();
        Filament::setCurrentPanel('homeowner');

        Livewire::test(RequestPasswordReset::class)
            ->fillForm(['email' => 'missing@example.test'])
            ->call('request')
            ->assertNotified('Check your email')
            ->assertSet('data.email', null);

        $wrongPanel = $this->userWithRole('hoa_admin');
        Livewire::test(RequestPasswordReset::class)
            ->fillForm(['email' => $wrongPanel->email])
            ->call('request')
            ->assertNotified('Check your email')
            ->assertSet('data.email', null);

        [$resident] = $this->homeowner(UserAccountStatus::Active);

        Livewire::test(RequestPasswordReset::class)
            ->fillForm(['email' => $resident->email])
            ->call('request')
            ->assertNotified('Check your email')
            ->assertSet('data.email', null);

        Notification::assertSentTo($resident, ResetPassword::class);
        Notification::assertNotSentTo($wrongPanel, ResetPassword::class);

        Livewire::test(RequestPasswordReset::class)
            ->fillForm(['email' => 'fourth@example.test'])
            ->call('request')
            ->assertNotified('Too many requests')
            ->assertSet('data.email', null);

        Filament::setCurrentPanel('admin');
        Livewire::test(RequestPasswordReset::class)
            ->fillForm(['email' => 'missing@example.test'])
            ->call('request')
            ->assertNotified('Check your email');
    }

    public function test_password_reset_revokes_existing_database_session_end_to_end(): void
    {
        config()->set('session.driver', 'database');
        [$resident] = $this->homeowner(UserAccountStatus::Active);
        $oldRememberToken = $resident->getRememberToken();
        $oldPasswordHash = $resident->getAuthPassword();

        $this->post('/portal/login', ['email' => $resident->email, 'password' => 'password'])
            ->assertRedirect('/portal/dashboard');
        $this->get('/portal/dashboard')->assertOk();
        $this->assertDatabaseHas('sessions', ['user_id' => $resident->id]);

        $resident->forceFill(['password' => 'NewSecureResident2026'])->saveQuietly();
        event(new PasswordReset($resident));

        $this->assertDatabaseMissing('sessions', ['user_id' => $resident->id]);
        $this->assertNotSame($oldRememberToken, $resident->refresh()->getRememberToken());
        $guard = Auth::guard('web');
        $guardSessionKey = $guard->getName();
        $sessionPasswordHash = $guard->hashPasswordForCookie($oldPasswordHash);
        Auth::forgetGuards();
        $this->withSession([
            $guardSessionKey => $resident->id,
            'password_hash_web' => $sessionPasswordHash,
        ])->get('/portal/dashboard')->assertRedirect('/portal/login');
    }

    public function test_authentication_fail_logout_and_password_reset_are_audited_without_credentials(): void
    {
        [$resident] = $this->homeowner(UserAccountStatus::Active);

        $this->post('/portal/login', ['email' => $resident->email, 'password' => 'wrong-password'])
            ->assertSessionHasErrors('email');
        $this->assertDatabaseHas('audit_logs', ['user_id' => $resident->id, 'action' => 'Auth.failed']);

        Auth::guard('web')->login($resident);
        Auth::guard('web')->logout();
        $this->assertDatabaseHas('audit_logs', ['user_id' => $resident->id, 'action' => 'Auth.logout']);

        event(new PasswordReset($resident));
        $this->assertDatabaseHas('audit_logs', ['user_id' => $resident->id, 'action' => 'Auth.password_reset']);
        $this->assertNotNull($resident->refresh()->password_changed_at);

        $logs = DB::table('audit_logs')->where('action', 'like', 'Auth.%')->get();
        $this->assertStringNotContainsString('wrong-password', $logs->toJson());
        $this->assertStringNotContainsString('password', strtolower((string) $logs->pluck('new_values')->implode(' ')));
    }

    /** @param array<string, mixed> $attributes */
    private function userWithRole(string $role, array $attributes = []): User
    {
        $user = User::factory()->create(['account_status' => 'Active', ...$attributes]);
        $user->assignRole($role);

        return $user;
    }

    /** @return array{User, Homeowner} */
    private function homeowner(UserAccountStatus $status): array
    {
        $user = User::factory()->create([
            'account_status' => $status->value,
            'email_verified_at' => $status === UserAccountStatus::Active ? now() : null,
        ]);
        $user->assignRole('homeowner');
        $homeowner = $user->homeowner()->create([
            'house_number' => '10',
            'street' => 'Mahogany',
            'residency_date' => '2021-01-01',
            'ownership_type' => 'Owner',
            'status' => $status === UserAccountStatus::Active ? 'Active' : 'Inactive',
        ]);

        return [$user, $homeowner];
    }
}
