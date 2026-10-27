<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\DuesObligation;
use App\Models\DuesSetting;
use App\Models\Household;
use App\Models\User;
use App\Support\PropertyAddress;
use App\Support\PropertyIdentity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

final class CanonicalPropertyPhaseMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_canonicalizes_current_confirmed_property_identity_without_rewriting_history(): void
    {
        $user = User::factory()->create(['account_status' => 'Active']);
        $owner = $user->homeowner()->create([
            'house_number' => '18', 'street' => 'Mahogany Street', 'block' => '4', 'lot' => '12',
            'phase' => PropertyAddress::DEFAULT_PHASE, 'phase_suffix' => 'A', 'residency_date' => '2020-06-01',
            'ownership_type' => 'Owner', 'status' => 'Active',
        ]);
        $household = Household::create([
            'identifier' => (string) Str::uuid(), 'address_key' => hash('sha256', 'legacy-household'),
            'house_number' => '18', 'street' => 'Mahogany Street', 'block' => '4', 'lot' => '12',
            'phase' => PropertyAddress::DEFAULT_PHASE, 'phase_suffix' => 'A',
            'registered_homeowner_id' => $owner->id,
        ]);
        $setting = DuesSetting::create([
            'name' => 'Migration snapshot', 'amount' => '100.00', 'frequency' => 'Monthly',
            'due_day' => 10, 'starts_on' => '2026-01-01', 'is_active' => true,
        ]);
        $obligation = DuesObligation::create([
            'homeowner_id' => $owner->id, 'dues_setting_id' => $setting->id,
            'billing_year' => 2026, 'billing_month' => 10, 'due_date' => '2026-10-10',
            'amount_due' => '100.00', 'penalty_amount' => '0.00', 'amount_paid' => '0.00', 'status' => 'Pending',
        ]);
        $snapshot = $obligation->property_snapshot;

        DB::table('homeowners')->where('id', $owner->id)->update(['phase' => '1', 'property_key' => hash('sha256', 'legacy-owner')]);
        DB::table('households')->where('id', $household->id)->update(['phase' => '1', 'property_key' => hash('sha256', 'legacy-household-key')]);

        $migration = require database_path('migrations/2026_10_26_000001_canonicalize_confirmed_property_phase.php');
        $migration->up();

        $canonicalKey = PropertyIdentity::confirmedKey(['phase' => PropertyAddress::DEFAULT_PHASE, 'phase_suffix' => 'A', 'block' => '4', 'lot' => '12']);
        $this->assertSame(PropertyAddress::DEFAULT_PHASE, $owner->fresh()->phase);
        $this->assertSame($canonicalKey, $owner->fresh()->property_key);
        $this->assertSame(PropertyAddress::DEFAULT_PHASE, $household->fresh()->phase);
        $this->assertSame($canonicalKey, $household->fresh()->property_key);
        $this->assertSame($snapshot, $obligation->fresh()->property_snapshot);
        $this->postJson(route('homeowner.register.landlord-availability'), [
            'phase_suffix' => 'A', 'street' => 'mahogany st.', 'block' => '4', 'lot' => '12',
        ])->assertOk()->assertJsonPath('options.0.label', $user->full_name);

        $this->expectException(RuntimeException::class);
        $migration->down();
    }
}
