<?php

namespace Tests\Support;

use App\Models\Complaint;
use App\Models\DuesObligation;
use App\Models\DuesSetting;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Notification;

final class Phase23BrowserSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('testing') || config('database.default') !== 'sqlite' || ! str_contains((string) config('database.connections.sqlite.database'), 'phase23-browser-test')) {
            throw new \RuntimeException('Browser fixtures require a separately named disposable SQLite test database.');
        }
        $this->call(RolesAndPermissionsSeeder::class);
        Notification::fake();
        $user = User::factory()->create(['email' => 'resident@phase23.test', 'password' => 'BrowserTest!2026', 'first_name' => 'Juan', 'last_name' => 'Dela Cruz', 'contact_number' => '09171234567', 'date_of_birth' => '1990-01-01']);
        $user->assignRole('homeowner');
        $user->homeowner()->create(['house_number' => '9', 'street' => 'Test Street', 'block' => '1', 'lot' => '2', 'phase' => 'Southville Phase I', 'ownership_type' => 'Owner', 'residency_date' => '2020-01-01', 'emergency_contact_name' => 'Maria Dela Cruz', 'emergency_contact_number' => '09987654321', 'status' => 'Active']);
        foreach (['admin' => 'hoa_admin', 'staff' => 'hoa_staff', 'unassigned' => 'hoa_staff'] as $name => $role) {
            $accounts[$name] = User::factory()->create(['email' => $name.'@phase23.test', 'password' => 'BrowserTest!2026', 'first_name' => ucfirst($name), 'last_name' => 'Browser Fixture']);
            $accounts[$name]->assignRole($role);
        }
        foreach (['coowner' => 'Co-owner', 'tenant' => 'Tenant', 'unrelated' => 'Owner'] as $name => $occupancy) {
            $member = User::factory()->create(['email' => $name.'@phase23.test', 'password' => 'BrowserTest!2026', 'first_name' => ucfirst($name), 'last_name' => 'Browser Fixture', 'date_of_birth' => '1991-02-03']);
            $member->assignRole('homeowner');
            $member->homeowner()->create([
                'house_number' => $name === 'unrelated' ? '99' : '9', 'street' => 'Test Street', 'block' => '1', 'lot' => '2', 'phase' => 'Southville Phase I',
                'ownership_type' => $occupancy, 'landlord_homeowner_id' => $occupancy === 'Tenant' ? $user->homeowner->id : null,
                'residency_date' => '2020-01-01', 'status' => 'Active',
            ]);
        }
        $setting = DuesSetting::create(['name' => 'Browser current dues', 'amount' => '489.00', 'frequency' => 'Monthly', 'starts_on' => today()->startOfMonth(), 'due_day' => 31, 'is_active' => true]);
        DuesObligation::firstOrCreate(['homeowner_id' => $user->homeowner->id, 'dues_setting_id' => $setting->id, 'billing_year' => today()->year, 'billing_month' => today()->month], [
            'due_date' => today()->endOfMonth(), 'amount_due' => '489.00', 'amount_paid' => '0.00', 'penalty_amount' => '0.00', 'status' => 'Pending',
        ]);
        Complaint::create(['homeowner_id' => $user->homeowner->id, 'subject' => 'Resolved browser concern', 'description' => 'Synthetic resolved concern for authorization testing only.', 'category' => 'Noise', 'priority' => 'Medium', 'status' => 'Resolved', 'handled_by' => $accounts['staff']->id, 'admin_remarks' => 'Previous resolution retained for review.', 'resolved_at' => now()]);
    }
}
