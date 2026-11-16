<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

final class SmsMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_additive_migration_preserves_existing_addresses_memberships_and_waiting_jobs(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        Notification::fake();
        Queue::fake();
        $user = User::factory()->create(['contact_number' => '09171234567']);
        $user->assignRole('homeowner');
        $owner = $user->homeowner()->create(['house_number' => '12', 'street' => 'Legacy Street', 'block' => '7', 'lot' => '8',
            'phase' => 'Southville Phase I', 'ownership_type' => 'Owner', 'residency_date' => '2020-01-01', 'status' => 'Active']);
        DB::table('jobs')->insert(['queue' => 'default', 'payload' => '{"synthetic":"do not run"}', 'attempts' => 0, 'available_at' => time(), 'created_at' => time()]);
        $before = [];
        foreach (['users', 'homeowners', 'households', 'household_members', 'jobs', 'failed_jobs'] as $table) {
            $before[$table] = DB::table($table)->orderBy('id')->get()->toJson();
        }
        $migration = require database_path('migrations/2026_11_09_000002_add_textbee_sms.php');
        $migration->down();
        $migration->up();
        foreach ($before as $table => $rows) {
            $this->assertSame($rows, DB::table($table)->orderBy('id')->get()->toJson(), $table);
        }
        $this->assertSame('Legacy Street', $owner->fresh()->street);
        $this->assertDatabaseCount('sms_contact_preferences', 0);
        $this->assertDatabaseCount('sms_deliveries', 0);
    }
}
