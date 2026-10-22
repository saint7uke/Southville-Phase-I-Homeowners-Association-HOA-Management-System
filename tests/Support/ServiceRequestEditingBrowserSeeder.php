<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

final class ServiceRequestEditingBrowserSeeder extends Seeder
{
    public function run(): void
    {
        $database = str_replace('\\', '/', (string) config('database.connections.sqlite.database'));
        if (! app()->environment('testing') || config('database.default') !== 'sqlite'
            || ! str_contains($database, '/storage/framework/testing/service-request-editing-browser-test-')
            || ! str_ends_with($database, '/database.sqlite') || User::query()->exists()) {
            throw new \RuntimeException('Requires a new, separately named service-request editing SQLite browser database.');
        }
        Notification::fake();
        $this->call(RolesAndPermissionsSeeder::class);
        foreach (['admin' => 'hoa_admin', 'staff' => 'hoa_staff', 'resident' => 'homeowner'] as $name => $role) {
            $accounts[$name] = User::factory()->create(['first_name' => ucfirst($name), 'last_name' => 'Synthetic Fixture', 'middle_name' => null,
                'email' => $name.'@request-edit.example.test', 'password' => 'BrowserTest!2026', 'preferred_locale' => 'en']);
            $accounts[$name]->assignRole($role);
        }
        $owner = $accounts['resident']->homeowner()->create(['street' => 'Test Street', 'block' => '1', 'lot' => '2',
            'phase' => 'Southville Phase I', 'phase_suffix' => 'A', 'ownership_type' => 'Owner', 'status' => 'Active', 'residency_date' => '2020-01-01']);
        foreach (['chromium', 'firefox', 'webkit'] as $project) {
            foreach (['staff', 'admin'] as $panel) {
                $owner->serviceRequests()->create(['request_type' => 'Certificate of Cohabitation', 'subject' => "Synthetic {$project} {$panel} approval flow",
                    'details' => 'Synthetic request for isolated save-and-refresh verification.', 'status' => 'Pending', 'handled_by' => $accounts['staff']->id,
                    'cohabitant_one' => $accounts['resident']->full_name, 'cohabitant_two' => 'Verified Synthetic Partner',
                    'address_snapshot' => $owner->only(['house_number', 'street', 'block', 'lot', 'phase', 'phase_suffix'])]);
            }
            $legacy = $owner->serviceRequests()->create(['request_type' => 'Repair/Maintenance', 'subject' => "Synthetic {$project} legacy completion",
                'details' => 'Synthetic grandfathered non-certificate service.', 'status' => 'Approved', 'handled_by' => $accounts['staff']->id]);
            DB::table('service_requests')->where('id', $legacy->id)->update(['grandfathered' => true]);
        }
        // Synthetic fixtures only: no payments, documents, source approvals or external notifications.
    }
}
