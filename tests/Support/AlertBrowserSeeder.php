<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Models\ContactMessage;
use App\Models\Project;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Database\Seeder;

final class AlertBrowserSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('testing') || config('database.default') !== 'sqlite'
            || ! str_contains((string) config('database.connections.sqlite.database'), 'hoa-alert-browser-')) {
            throw new \RuntimeException('Alert browser fixtures require a dedicated temporary SQLite database.');
        }
        $this->call(RolesAndPermissionsSeeder::class);
        foreach (['hoa_admin' => 'admin', 'hoa_staff' => 'staff', 'homeowner' => 'resident'] as $role => $name) {
            $user = User::factory()->create(['first_name' => 'Alert', 'last_name' => ucfirst($name), 'email' => $name.'@alerts.example.test', 'password' => 'AlertFixture!2026']);
            $user->assignRole($role);
            if ($role === 'homeowner') {
                $user->homeowner()->create(['house_number' => '12', 'street' => 'Fixture Street', 'block' => '7', 'lot' => '8', 'phase' => 'Southville Phase I', 'phase_suffix' => 'A',
                    'ownership_type' => 'Owner', 'residency_date' => '2020-01-01', 'status' => 'Active', 'emergency_contact_name' => 'Fixture Contact', 'emergency_contact_number' => '09171234567']);
            }
        }
        Project::create(['title' => 'Alert browser project', 'description' => 'Disposable browser test record.', 'completion_status' => 'Planned', 'project_date' => today()]);
        $message = ContactMessage::create(['name' => 'Fixture Sender', 'email' => 'sender@alerts.example.test', 'subject' => 'Archived alert fixture', 'message' => 'Disposable inquiry.', 'status' => 'New']);
        $message->delete();
    }
}
