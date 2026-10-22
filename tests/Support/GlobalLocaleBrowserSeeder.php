<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Notification;

final class GlobalLocaleBrowserSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('testing') || config('database.default') !== 'sqlite'
            || ! str_contains((string) config('database.connections.sqlite.database'), 'phase23-browser-test-shared-receipts-global-locale')) {
            throw new \RuntimeException('Only the separately named disposable global locale browser database is permitted.');
        }
        Notification::fake();
        $this->call(RolesAndPermissionsSeeder::class);
        foreach (['admin' => 'hoa_admin', 'staff' => 'hoa_staff', 'resident' => 'homeowner'] as $name => $role) {
            $user = User::factory()->create(['email' => $name.'@locale.example.test', 'password' => 'BrowserTest!2026',
                'first_name' => ucfirst($name), 'middle_name' => null, 'last_name' => 'Locale Fixture', 'preferred_locale' => 'en',
                'date_of_birth' => '1990-01-01', 'contact_number' => '09171234567']);
            $user->assignRole($role);
            if ($role === 'homeowner') {
                $user->homeowner()->create(['street' => 'Test Street', 'block' => '1', 'lot' => '2', 'phase' => 'Southville Phase I',
                    'phase_suffix' => 'A', 'ownership_type' => 'Owner', 'status' => 'Active', 'residency_date' => '2020-01-01',
                    'emergency_contact_name' => 'Maria Fixture', 'emergency_contact_number' => '09987654321']);
            }
        }
        // No financial rows, document approvals, outgoing mail or real residents.
    }
}
