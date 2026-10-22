<?php

namespace Tests\Support;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Notification;

final class CohabitationPhaseBrowserSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('testing') || config('database.default') !== 'sqlite'
            || ! str_contains((string) config('database.connections.sqlite.database'), 'phase23-browser-test-shared-receipts-cohabphase')) {
            throw new \RuntimeException('Requires the separately named disposable Cohabitation browser database.');
        }
        $this->call(RolesAndPermissionsSeeder::class);
        Notification::fake();
        foreach (['admin' => 'hoa_admin', 'staff' => 'hoa_staff'] as $name => $role) {
            User::factory()->create(['first_name' => ucfirst($name), 'middle_name' => null, 'last_name' => 'Test Fixture',
                'email' => $name.'@phase23.test', 'password' => 'BrowserTest!2026'])->assignRole($role);
        }
        foreach ([['Juan', 'Dela Cruz', '1990-01-01', 'Owner'], ['Maria', 'Santos', '1991-02-03', 'Co-owner']] as [$first, $last, $birth, $occupancy]) {
            $user = User::factory()->create(['first_name' => $first, 'middle_name' => null, 'last_name' => $last, 'date_of_birth' => $birth,
                'email' => strtolower($first).'@phase23.test', 'password' => 'BrowserTest!2026']);
            $user->assignRole('homeowner');
            $user->homeowner()->create(['street' => 'Test Street', 'block' => '1', 'lot' => '2', 'phase' => 'Southville Phase I',
                'phase_suffix' => 'B', 'ownership_type' => $occupancy, 'status' => 'Active', 'residency_date' => '2020-01-01']);
        }
        // No document/source approval, payment, obligation or external notification is seeded.
    }
}
