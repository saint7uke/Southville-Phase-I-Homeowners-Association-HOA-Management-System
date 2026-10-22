<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Homeowner;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class PropertyIdentityMigrationTest extends TestCase
{
    use DatabaseMigrations;

    public function test_populated_nullable_migration_preserves_accounts_memberships_and_legacy_keys(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = User::factory()->create(['account_status' => 'Active']);
        $user->assignRole('homeowner');
        $owner = Homeowner::create(['user_id' => $user->id, 'house_number' => '12', 'street' => 'Test Street', 'phase' => 'Southville Phase I',
            'block' => '1', 'lot' => '2', 'ownership_type' => 'Owner', 'residency_date' => '2020-01-01', 'status' => 'Active']);
        $legacyKey = $owner->address_key;
        $households = DB::table('households')->get()->toArray();
        $members = DB::table('household_members')->get()->toArray();
        $migration = require database_path('migrations/2026_10_20_000006_add_confirmed_property_identity.php');
        $migration->down();
        $this->artisan('hoa:review-property-identities', ['--json' => true])->expectsOutputToContain('"read_only": true')->assertSuccessful();
        // Reproduce the populated pre-change NOT NULL schema outside any test transaction.
        Schema::withoutForeignKeyConstraints(function (): void {
            foreach (['homeowners', 'households'] as $tableName) {
                Schema::table($tableName, fn (Blueprint $table) => $table->string('house_number', 50)->nullable(false)->change());
            }
        });
        $migration->up();
        $this->assertSame('12', $owner->fresh()->house_number);
        $this->assertSame($legacyKey, $owner->fresh()->address_key);
        $this->assertNull($owner->fresh()->phase_suffix);
        $this->assertNull($owner->fresh()->property_key);
        $this->assertEquals($members, DB::table('household_members')->get()->toArray());
        foreach ($households as $household) {
            $this->assertEquals((array) $household, (array) DB::table('households')->where('id', $household->id)->first(array_keys((array) $household)));
        }
        if (DB::connection()->getDriverName() === 'sqlite') {
            $this->assertSame([], DB::select('PRAGMA foreign_key_check'));
            $this->assertSame(1, (int) DB::selectOne('PRAGMA foreign_keys')->foreign_keys);
        }
    }
}
