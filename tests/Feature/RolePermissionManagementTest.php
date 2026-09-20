<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Roles\UpdateRolePermissions;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class RolePermissionManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_admin_can_edit_safe_role_permissions_and_change_is_audited(): void
    {
        $admin = User::factory()->create(['account_status' => 'Active']);
        $admin->assignRole('hoa_admin');
        $staffRole = Role::query()->where('name', 'hoa_staff')->firstOrFail();
        $permissions = $staffRole->permissions()->pluck('name')->reject(fn (string $name): bool => $name === 'export_reports')->values()->all();

        app(UpdateRolePermissions::class)->handle($staffRole, $permissions, $admin);

        $this->assertFalse($staffRole->refresh()->hasPermissionTo('export_reports'));
        $this->assertDatabaseHas('audit_logs', ['user_id' => $admin->id, 'action' => 'Role.permissions_changed', 'auditable_id' => $staffRole->id]);
        $this->actingAs($admin, 'admin')->get('/admin/roles')->assertOk();
    }

    public function test_staff_cannot_receive_admin_permissions_and_cannot_open_role_resource(): void
    {
        $admin = User::factory()->create(['account_status' => 'Active']);
        $admin->assignRole('hoa_admin');
        $staff = User::factory()->create(['account_status' => 'Active']);
        $staff->assignRole('hoa_staff');
        $staffRole = Role::query()->where('name', 'hoa_staff')->firstOrFail();

        try {
            app(UpdateRolePermissions::class)->handle($staffRole, [...$staffRole->permissions()->pluck('name')->all(), 'manage_users'], $admin);
            $this->fail('Staff cannot receive administrator-only permissions.');
        } catch (ValidationException) {
            $this->assertFalse($staffRole->refresh()->hasPermissionTo('manage_users'));
        }

        $this->actingAs($staff, 'staff')->get('/staff/roles')->assertNotFound();
    }
}
