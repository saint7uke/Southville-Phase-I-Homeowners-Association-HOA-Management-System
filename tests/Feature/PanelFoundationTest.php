<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Announcement;
use App\Models\SystemSetting;
use App\Models\User;
use App\Support\AuthenticatedActor;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class PanelFoundationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    /** @return array<string, array{string, string, string, bool}> */
    public static function rolePanelAccessProvider(): array
    {
        return [
            'admin in admin' => ['hoa_admin', 'admin', '/admin', true],
            'admin in staff' => ['hoa_admin', 'staff', '/staff', false],
            'admin in homeowner' => ['hoa_admin', 'homeowner', '/homeowner', false],
            'staff in admin' => ['hoa_staff', 'admin', '/admin', false],
            'staff in staff' => ['hoa_staff', 'staff', '/staff', true],
            'staff in homeowner' => ['hoa_staff', 'homeowner', '/homeowner', false],
            'homeowner in admin' => ['homeowner', 'admin', '/admin', false],
            'homeowner in staff' => ['homeowner', 'staff', '/staff', false],
            'homeowner in homeowner' => ['homeowner', 'homeowner', '/homeowner', true],
        ];
    }

    #[DataProvider('rolePanelAccessProvider')]
    public function test_each_role_can_access_only_its_panel(string $role, string $guard, string $path, bool $allowed): void
    {
        $user = User::factory()->create(['account_status' => 'Active']);
        $user->assignRole($role);

        $response = $this->actingAs($user, $guard)->get($path);

        $allowed ? $response->assertOk() : $response->assertForbidden();
    }

    public function test_actor_resolver_uses_the_web_guard_outside_filament(): void
    {
        $portalUser = User::factory()->create();
        $unrelatedAdmin = User::factory()->create();
        Auth::guard('web')->setUser($portalUser);
        Auth::guard('admin')->setUser($unrelatedAdmin);

        $this->assertSame($portalUser->id, app(AuthenticatedActor::class)->id());
    }

    public function test_actor_resolver_does_not_scan_panel_guards_outside_filament(): void
    {
        $admin = User::factory()->create();
        Auth::guard('admin')->setUser($admin);

        $this->assertNull(app(AuthenticatedActor::class)->id());
    }

    public function test_actor_resolver_uses_only_the_current_filament_panel_guard(): void
    {
        $portalUser = User::factory()->create();
        $admin = User::factory()->create();
        Auth::guard('web')->setUser($portalUser);
        Auth::guard('admin')->setUser($admin);
        Filament::setCurrentPanel('admin');

        $this->assertSame($admin->id, app(AuthenticatedActor::class)->id());
    }

    public function test_model_actor_stamps_use_the_panel_guard(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('hoa_admin');
        Auth::guard('admin')->setUser($admin);
        Filament::setCurrentPanel('admin');

        $announcement = Announcement::query()->create([
            'title' => 'Guard-aware author',
            'content' => 'This announcement verifies panel actor attribution.',
            'category' => 'Notice',
            'status' => 'Draft',
        ]);

        $this->assertSame($admin->id, $announcement->created_by);
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $admin->id,
            'auditable_type' => Announcement::class,
            'auditable_id' => $announcement->id,
            'action' => 'Announcement.created',
        ]);
    }

    public function test_panel_guards_continue_to_use_web_guard_permissions(): void
    {
        $admin = User::factory()->create(['account_status' => 'Active']);
        $admin->assignRole('hoa_admin');

        $this->actingAs($admin, 'admin')->get('/admin/users')->assertOk();

        $staff = User::factory()->create(['account_status' => 'Active']);
        $staff->assignRole('hoa_staff');

        $this->actingAs($staff, 'staff')->get('/staff/homeowners')->assertOk();
    }

    public function test_legacy_admin_session_transfers_once_and_is_consumed(): void
    {
        $admin = User::factory()->create(['account_status' => 'Active']);
        $admin->assignRole('hoa_admin');

        $this->actingAs($admin, 'web')->get('/admin')->assertOk();

        $this->assertGuest('web');
        $this->assertAuthenticatedAs($admin, 'admin');
    }

    public function test_panel_logout_cannot_reauthenticate_from_a_consumed_legacy_session(): void
    {
        $admin = User::factory()->create(['account_status' => 'Active']);
        $admin->assignRole('hoa_admin');
        $this->actingAs($admin, 'web')->get('/admin')->assertOk();

        $this->post('/admin/logout')->assertRedirect();

        $this->assertGuest('web');
        $this->assertGuest('admin');
        $this->get('/admin')->assertRedirect('/admin/login');
    }

    public function test_mismatched_legacy_session_can_still_reach_panel_login(): void
    {
        $staff = User::factory()->create(['account_status' => 'Active']);
        $staff->assignRole('hoa_staff');

        $this->actingAs($staff, 'web')->get('/admin/login')->assertOk();

        $this->assertAuthenticatedAs($staff, 'web');
        $this->assertGuest('admin');
    }

    public function test_legacy_transfer_does_not_overwrite_an_authenticated_panel_guard(): void
    {
        $panelAdmin = User::factory()->create(['account_status' => 'Active']);
        $panelAdmin->assignRole('hoa_admin');
        $legacyAdmin = User::factory()->create(['account_status' => 'Active']);
        $legacyAdmin->assignRole('hoa_admin');
        Auth::guard('admin')->login($panelAdmin);
        Auth::guard('web')->login($legacyAdmin);

        $this->get('/admin')->assertOk();

        $this->assertAuthenticatedAs($panelAdmin, 'admin');
        $this->assertAuthenticatedAs($legacyAdmin, 'web');
    }

    /** @return array<string, array{string, string}> */
    public static function rejectedLegacyTransferProvider(): array
    {
        return [
            'wrong role' => ['hoa_staff', 'Active'],
            'inactive admin' => ['hoa_admin', 'Inactive'],
        ];
    }

    #[DataProvider('rejectedLegacyTransferProvider')]
    public function test_wrong_role_or_inactive_legacy_user_is_not_migrated(string $role, string $status): void
    {
        $user = User::factory()->create(['account_status' => $status]);
        $user->assignRole($role);

        $this->actingAs($user, 'web')->get('/admin/login')->assertOk();

        $this->assertAuthenticatedAs($user, 'web');
        $this->assertGuest('admin');
    }

    public function test_homeowner_legacy_session_is_not_migrated_to_filament(): void
    {
        $homeowner = User::factory()->create(['account_status' => 'Active']);
        $homeowner->assignRole('homeowner');

        $this->actingAs($homeowner, 'web')->get('/homeowner/login')->assertOk();

        $this->assertAuthenticatedAs($homeowner, 'web');
        $this->assertGuest('homeowner');
    }

    public function test_inactive_homeowner_is_forbidden_from_homeowner_panel(): void
    {
        $homeowner = User::factory()->create(['account_status' => 'Inactive']);
        $homeowner->assignRole('homeowner');

        $this->actingAs($homeowner, 'homeowner')->get('/homeowner')->assertForbidden();
    }

    public function test_admin_can_disable_staff_and_homeowner_panels_without_disabling_admin(): void
    {
        SystemSetting::current()->update(['staff_panel_enabled' => false, 'homeowner_panel_enabled' => false]);
        $admin = User::factory()->create(['account_status' => 'Active']);
        $admin->assignRole('hoa_admin');
        $staff = User::factory()->create(['account_status' => 'Active']);
        $staff->assignRole('hoa_staff');
        $homeowner = User::factory()->create(['account_status' => 'Active']);
        $homeowner->assignRole('homeowner');

        $this->actingAs($staff, 'staff')->get('/staff')->assertStatus(503);
        $this->actingAs($homeowner, 'homeowner')->get('/homeowner')->assertStatus(503);
        $this->actingAs($admin, 'admin')->get('/admin')->assertOk();
    }
}
