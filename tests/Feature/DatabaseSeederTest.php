<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\DuesSetting;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_dues_schedule_has_a_required_start_date_and_seeding_is_repeatable(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->seed(DatabaseSeeder::class);

        $setting = DuesSetting::query()->where('name', 'Monthly Association Dues')->sole();

        $this->assertNotNull($setting->starts_on);
        $this->assertSame(today()->startOfMonth()->toDateString(), $setting->starts_on->toDateString());
        $this->assertSame(1, DuesSetting::query()->where('normalized_name', 'monthly association dues')->count());
    }
}
