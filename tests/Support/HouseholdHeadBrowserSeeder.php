<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Models\Household;
use Illuminate\Database\Seeder;

final class HouseholdHeadBrowserSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(AlertBrowserSeeder::class);
        $household = Household::sole();
        foreach (['Head Browser Candidate', 'Head Browser Second'] as $name) {
            $household->members()->create(['full_name' => $name, 'relationship' => 'Other', 'residency_status' => 'Resident']);
        }
    }
}
