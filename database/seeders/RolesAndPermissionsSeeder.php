<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

final class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $admin = ['manage_users', 'approve_homeowner_registrations', 'manage_settings', 'view_audit_logs', 'manage_homeowners', 'delete_homeowners', 'manage_payments', 'delete_payments', 'export_reports', 'manage_complaints', 'manage_requests', 'manage_announcements', 'publish_announcements', 'view_published_announcements'];
        $resident = ['submit_own_complaint', 'view_own_complaints', 'submit_own_request', 'view_own_requests', 'view_published_announcements', 'update_own_profile'];

        collect([...$admin, ...$resident])->unique()->each(fn (string $name) => Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']));
        Role::firstOrCreate(['name' => 'hoa_admin', 'guard_name' => 'web'])->syncPermissions($admin);
        Role::firstOrCreate(['name' => 'hoa_staff', 'guard_name' => 'web'])->syncPermissions(['manage_homeowners', 'manage_payments', 'export_reports', 'manage_complaints', 'manage_requests', 'view_published_announcements']);
        Role::firstOrCreate(['name' => 'homeowner', 'guard_name' => 'web'])->syncPermissions($resident);
    }
}
