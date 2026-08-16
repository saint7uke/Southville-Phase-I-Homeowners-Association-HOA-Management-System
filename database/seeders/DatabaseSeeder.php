<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Announcement;
use App\Models\DuesSetting;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

final class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(RolesAndPermissionsSeeder::class);

        $admin = User::query()->firstOrCreate(['email' => 'admin@southville.test'], [
            'first_name' => 'Maria', 'middle_name' => 'Santos', 'last_name' => 'Reyes', 'sex' => 'Female',
            'contact_number' => '09171234567', 'date_of_birth' => '1984-05-16', 'password' => Hash::make('Southville2026!'),
            'account_status' => 'Active', 'email_verified_at' => now(), 'approved_at' => now(),
        ]);
        $admin->syncRoles(['hoa_admin']);

        $staff = User::query()->firstOrCreate(['email' => 'staff@southville.test'], [
            'first_name' => 'Joel', 'last_name' => 'Mendoza', 'sex' => 'Male', 'contact_number' => '09181234567',
            'date_of_birth' => '1991-10-08', 'password' => Hash::make('Southville2026!'), 'account_status' => 'Active',
            'email_verified_at' => now(), 'approved_at' => now(), 'approved_by' => $admin->id,
        ]);
        $staff->syncRoles(['hoa_staff']);

        $resident = User::query()->firstOrCreate(['email' => 'resident@southville.test'], [
            'first_name' => 'Angela', 'last_name' => 'Navarro', 'sex' => 'Female', 'contact_number' => '09191234567',
            'date_of_birth' => '1993-03-22', 'password' => Hash::make('Southville2026!'), 'account_status' => 'Active',
            'email_verified_at' => now(), 'approved_at' => now(), 'approved_by' => $admin->id,
        ]);
        $resident->syncRoles(['homeowner']);
        $resident->homeowner()->firstOrCreate([], ['house_number' => '18', 'street' => 'Mahogany Street', 'block' => '4', 'lot' => '12', 'residency_date' => '2020-06-01', 'ownership_type' => 'Owner', 'status' => 'Active']);

        DuesSetting::query()->firstOrCreate(['name' => 'Monthly Association Dues'], ['amount' => 500, 'frequency' => 'Monthly', 'is_active' => true]);
        Announcement::query()->firstOrCreate(['title' => 'Community clean-up drive'], [
            'content' => 'Join your neighbors for our community clean-up drive this Saturday at 7:00 AM. Assembly is at the HOA office.',
            'category' => 'Event', 'status' => 'Published', 'published_at' => now()->subDay(), 'created_by' => $admin->id,
        ]);
    }
}
