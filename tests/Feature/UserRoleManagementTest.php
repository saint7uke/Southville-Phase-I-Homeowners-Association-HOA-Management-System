<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Users\ChangeUserRole;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class UserRoleManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_admin_can_change_another_users_role_and_it_is_audited(): void
    {
        $admin = $this->user('hoa_admin');
        $staff = $this->user('hoa_staff');
        $staff->homeowner()->create([
            'house_number' => '10',
            'street' => 'Role Street',
            'block' => '10',
            'lot' => '10',
            'phase' => 'Southville Phase I',
            'residency_date' => '2020-01-01',
            'ownership_type' => 'Owner',
            'emergency_contact_name' => 'Contact Person',
            'emergency_contact_number' => '09123456789',
            'status' => 'Inactive',
        ]);

        app(ChangeUserRole::class)->handle($staff, 'homeowner', $admin);

        $this->assertTrue($staff->refresh()->hasRole('homeowner'));
        $this->assertDatabaseHas('audit_logs', ['user_id' => $admin->id, 'action' => 'User.role_changed', 'auditable_id' => $staff->id]);
    }

    public function test_last_active_admin_and_self_role_changes_are_rejected(): void
    {
        $admin = $this->user('hoa_admin');
        $this->expectException(ValidationException::class);
        app(ChangeUserRole::class)->handle($admin, 'hoa_staff', $admin);
    }

    private function user(string $role): User
    {
        $user = User::factory()->create(['account_status' => 'Active']);
        $user->assignRole($role);

        return $user;
    }
}
