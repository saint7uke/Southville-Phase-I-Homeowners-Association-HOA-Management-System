<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Models\Household;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Notification;

final class MyHouseholdBrowserSeeder extends Seeder
{
    public function run(): void
    {
        $database = str_replace('\\', '/', (string) config('database.connections.sqlite.database'));
        if (! app()->environment('testing') || config('database.default') !== 'sqlite'
            || ! str_contains($database, '/storage/framework/testing/my-household-browser-test-')
            || ! str_ends_with($database, '/database.sqlite') || User::query()->exists()) {
            throw new \RuntimeException('Requires a fresh isolated My Household SQLite browser database.');
        }

        Notification::fake();
        $this->call(RolesAndPermissionsSeeder::class);
        foreach (['large' => 25, 'small' => 10, 'unavailable' => 1, 'unrelated' => 1] as $name => $count) {
            $user = User::factory()->create(['first_name' => 'AAA', 'middle_name' => null, 'last_name' => ucfirst($name).' Resident', 'suffix' => null,
                'email' => $name.'@household.example.test', 'password' => 'SyntheticHousehold!2026', 'preferred_locale' => 'en']);
            $user->assignRole('homeowner');
            $resident = $user->homeowner()->create(['street' => 'English Resident Street Kept Unchanged', 'block' => '1', 'lot' => (string) $user->id,
                'phase' => 'Southville Phase I', 'phase_suffix' => 'A', 'ownership_type' => 'Owner', 'status' => 'Active', 'residency_date' => '2020-01-01']);
            $household = Household::query()->where('property_key', $resident->property_key)->firstOrFail();
            for ($index = 1; $index < $count; $index++) {
                $household->members()->create(['full_name' => sprintf('Member %02d English Name', $index),
                    'relationship' => 'Child', 'residency_status' => $index % 2 ? 'Resident' : 'Temporarily away',
                    'date_of_birth' => '1999-02-03', 'sex' => 'Female']);
            }
            $household->members()->create(['full_name' => 'Ended Secret Member', 'relationship' => 'Other', 'residency_status' => 'Moved', 'ended_at' => now()]);
            if ($name === 'unavailable') {
                $household->members()->where('current_homeowner_id', $resident->id)->update(['ended_at' => now(), 'current_homeowner_id' => null]);
            }
        }
    }
}
