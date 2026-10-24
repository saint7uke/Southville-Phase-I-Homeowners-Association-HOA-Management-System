<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Dues\GenerateSettingObligations;
use App\Models\DuesObligation;
use App\Models\DuesSetting;
use App\Models\Homeowner;
use App\Models\User;
use App\Services\Reporting\HoaReportCatalog;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use LogicException;
use Tests\TestCase;

final class HistoricalPropertySnapshotTest extends TestCase
{
    use RefreshDatabase;

    public function test_generated_obligation_keeps_an_immutable_historical_property_snapshot(): void
    {
        [$owner, $setting] = $this->property();

        $this->assertSame(1, app(GenerateSettingObligations::class)->handle($setting, CarbonImmutable::create(2026, 10, 1)));
        $obligation = DuesObligation::sole();
        $this->assertSame($owner->property_key, $obligation->property_snapshot['property_key']);
        $this->assertStringContainsString('Block 7 Lot 8', $obligation->property_address);
        $this->assertFalse($obligation->property_snapshot_needs_review);

        $owner->forceFill(['block' => '9', 'lot' => '10'])->saveQuietly();
        $this->assertStringContainsString('Block 7 Lot 8', $obligation->fresh()->property_address);

        $this->expectException(LogicException::class);
        $obligation->update(['property_snapshot' => ['display_address' => 'Forged address']]);
    }

    public function test_delinquency_report_prefers_the_obligation_snapshot(): void
    {
        [$owner, $setting] = $this->property();
        $obligation = DuesObligation::create([
            'homeowner_id' => $owner->id, 'dues_setting_id' => $setting->id,
            'billing_year' => 2026, 'billing_month' => 10, 'due_date' => '2026-10-01',
            'amount_due' => '100.00', 'penalty_amount' => '0.00', 'amount_paid' => '0.00', 'status' => 'Overdue',
        ]);
        $owner->forceFill(['block' => '99', 'lot' => '99'])->saveQuietly();
        $catalog = app(HoaReportCatalog::class);
        $filters = $catalog->validateFilters('delinquency', [], true);
        $row = $catalog->map('delinquency', $catalog->query('delinquency', $filters)->findOrFail($obligation->id));

        $this->assertStringContainsString('Block 7 Lot 8', $row[1]);
        $this->assertStringNotContainsString('Block 99', $row[1]);
    }

    public function test_integrity_audit_reports_reviewed_legacy_state_separately(): void
    {
        [$owner, $setting] = $this->property();
        $obligation = DuesObligation::create([
            'homeowner_id' => $owner->id, 'dues_setting_id' => $setting->id,
            'billing_year' => 2026, 'billing_month' => 10, 'due_date' => '2026-10-31',
            'amount_due' => '100.00', 'penalty_amount' => '0.00', 'amount_paid' => '0.00', 'status' => 'Pending',
        ]);
        DuesObligation::whereKey($obligation->id)->update(['property_snapshot' => null, 'property_snapshot_needs_review' => true]);

        $this->assertSame(0, Artisan::call('hoa:audit-data', ['--json' => true]));
        $output = Artisan::output();
        $this->assertStringContainsString('"obligations_missing_property_snapshot_state": 0', $output);
        $this->assertStringContainsString('"obligations_requiring_property_snapshot_review": 1', $output);
    }

    /** @return array{Homeowner, DuesSetting} */
    private function property(): array
    {
        $user = User::factory()->create(['account_status' => 'Active']);
        $owner = $user->homeowner()->create([
            'house_number' => '12', 'street' => 'Snapshot Street', 'block' => '7', 'lot' => '8',
            'phase' => 'Southville Phase I', 'phase_suffix' => 'A', 'residency_date' => '2020-01-01',
            'ownership_type' => 'Owner', 'status' => 'Active',
        ]);
        $setting = DuesSetting::create([
            'name' => 'Snapshot Dues', 'amount' => '100.00', 'frequency' => 'Monthly',
            'due_day' => 10, 'starts_on' => '2026-01-01', 'is_active' => true,
        ]);

        return [$owner, $setting];
    }
}
