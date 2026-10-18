<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\GenerateHoaReport;
use App\Models\Announcement;
use App\Models\Complaint;
use App\Models\DuesObligation;
use App\Models\DuesSetting;
use App\Models\Homeowner;
use App\Models\Payment;
use App\Models\ReportExport;
use App\Models\User;
use App\Services\Reporting\HoaReportCatalog;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class AdminReportingExpansionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_every_catalog_report_builds_a_query_and_summary(): void
    {
        $catalog = app(HoaReportCatalog::class);

        foreach ($catalog->definitions() as $slug => $definition) {
            $filters = $catalog->validateFilters($slug, [], true);
            $this->assertIsArray($catalog->headings($slug));
            $this->assertNotEmpty($catalog->headings($slug));
            $this->assertCount(0, $catalog->query($slug, $filters)->limit(1)->get(), $slug);
            $this->assertIsArray($catalog->summary($slug, $filters), $slug);
            $this->assertContains('xlsx', $definition['formats']);
        }
    }

    public function test_property_ledger_totals_use_obligations_and_exact_cent_arithmetic(): void
    {
        [$resident, $homeowner] = $this->resident('Ledger', 'Owner');
        $setting = $this->setting();
        DuesObligation::query()->create([
            'homeowner_id' => $homeowner->id,
            'dues_setting_id' => $setting->id,
            'billing_year' => 2026,
            'billing_month' => 10,
            'due_date' => '2026-10-31',
            'amount_due' => '1000.10',
            'penalty_amount' => '50.20',
            'amount_paid' => '400.30',
            'status' => 'Partial',
        ]);

        $catalog = app(HoaReportCatalog::class);
        $filters = $catalog->validateFilters('payment-homeowner', [], true);
        $row = $catalog->query('payment-homeowner', $filters)->sole();

        $this->assertSame(100010, Money::toMinor((string) $row->total_assessed));
        $this->assertSame(40030, Money::toMinor((string) $row->total_paid));
        $this->assertSame(65000, Money::toMinor((string) $row->total_outstanding));
        $this->assertSame([
            'Rows' => 1,
            'Assessed' => '1000.10',
            'Paid' => '400.30',
            'Outstanding' => '650.00',
        ], $catalog->summary('payment-homeowner', $filters));
        $this->assertSame($resident->full_name, $catalog->map('payment-homeowner', $row)[0]);
    }

    public function test_collections_include_only_current_accepted_payments_and_reviews_remain_separate(): void
    {
        [$resident, $homeowner] = $this->resident('Payment', 'Owner');
        $admin = $this->userWithRole('hoa_admin');
        $setting = $this->setting();

        $approved = $this->payment($homeowner->id, $setting->id, $admin->id, '100.10', 'Approved', 'proofs/approved.pdf');
        $recorded = $this->payment($homeowner->id, $setting->id, $admin->id, '200.20', 'Recorded');
        $this->payment($homeowner->id, $setting->id, $admin->id, '300.30', 'Pending', 'proofs/pending.pdf');
        $this->payment($homeowner->id, $setting->id, $admin->id, '400.40', 'Rejected', 'proofs/rejected.pdf');
        $archived = $this->payment($homeowner->id, $setting->id, $admin->id, '500.50', 'Approved', 'proofs/archived.pdf');
        $archived->delete();

        $catalog = app(HoaReportCatalog::class);
        $collectionFilters = $catalog->validateFilters('payment-history', [], true);
        $this->assertEqualsCanonicalizing([$approved->id, $recorded->id], $catalog->query('payment-history', $collectionFilters)->pluck('id')->all());
        $this->assertSame('300.30', $catalog->summary('payment-history', $collectionFilters)['Accepted collections']);

        $reviewFilters = $catalog->validateFilters('payment-review', [], true);
        $this->assertCount(3, $catalog->query('payment-review', $reviewFilters)->get());
        $this->assertSame('300.30', $catalog->summary('payment-review', $reviewFilters)['Pending reserved']);

        $archivedFilters = $catalog->validateFilters('payment-history', ['scope' => 'archived'], true);
        $this->assertSame([$archived->id], $catalog->query('payment-history', $archivedFilters)->pluck('id')->all());
        $this->assertSame($resident->full_name, $catalog->map('payment-history', $approved)[1]);
    }

    public function test_announcement_report_uses_unique_reads_and_current_active_resident_reach(): void
    {
        $admin = $this->userWithRole('hoa_admin');
        $reader = $this->userWithRole('homeowner');
        $this->userWithRole('homeowner');
        $inactiveReader = $this->userWithRole('homeowner');
        $inactiveReader->forceFill(['account_status' => 'Inactive'])->save();
        $announcement = Announcement::query()->create([
            'title' => 'Water service advisory',
            'content' => 'Scheduled maintenance details.',
            'category' => 'Maintenance',
            'audience' => 'Residents',
            'status' => 'Published',
            'published_at' => now(),
            'created_by' => $admin->id,
        ]);
        $announcement->readers()->attach($reader->id, ['read_at' => now()]);
        $announcement->readers()->attach($inactiveReader->id, ['read_at' => now()]);

        $catalog = app(HoaReportCatalog::class);
        $filters = $catalog->validateFilters('announcements', ['q' => 'water'], true);
        $row = $catalog->query('announcements', $filters)->sole();
        $mapped = $catalog->map('announcements', $row);

        $this->assertSame(2, $mapped[7]);
        $this->assertSame('50.0%', $mapped[8]);
    }

    public function test_queued_xlsx_is_private_and_format_aware(): void
    {
        Storage::fake('local');
        $admin = $this->userWithRole('hoa_admin');
        $export = ReportExport::query()->create([
            'user_id' => $admin->id,
            'panel' => 'admin',
            'report' => 'homeowners',
            'format' => 'xlsx',
            'filters' => [],
            'status' => 'Resolved',
        ]);

        (new GenerateHoaReport($export->id))->handle();

        $this->assertSame('Completed', $export->refresh()->status);
        $this->assertStringEndsWith('.xlsx', (string) $export->file_path);
        Storage::disk('local')->assertExists($export->file_path);
        $this->actingAs($admin, 'admin')
            ->get(route('admin.reports.queued.download', $export))
            ->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    public function test_search_status_dates_sorting_and_archive_scope_share_one_query_contract(): void
    {
        [, $homeowner] = $this->resident('Filtered', 'Owner');
        $start = CarbonImmutable::parse('2026-10-18 00:00:00', 'Asia/Manila');
        $end = CarbonImmutable::parse('2026-10-18 23:59:59', 'Asia/Manila');
        $first = Complaint::query()->create([
            'homeowner_id' => $homeowner->id,
            'ticket_number' => 'CMP-FILTER-001',
            'subject' => 'Boundary drainage issue',
            'description' => 'Testing the inclusive beginning of the selected day.',
            'category' => 'Common Area',
            'priority' => 'Medium',
            'status' => 'Resolved',
        ]);
        $first->forceFill(['created_at' => $start])->saveQuietly();
        $second = Complaint::query()->create([
            'homeowner_id' => $homeowner->id,
            'ticket_number' => 'CMP-FILTER-002',
            'subject' => 'Boundary drainage follow-up',
            'description' => 'Testing the inclusive end of the selected day.',
            'category' => 'Common Area',
            'priority' => 'High',
            'status' => 'Resolved',
        ]);
        $second->forceFill(['created_at' => $end])->saveQuietly();
        $archived = Complaint::query()->create([
            'homeowner_id' => $homeowner->id,
            'ticket_number' => 'CMP-ARCHIVE-001',
            'subject' => 'Archived drainage item',
            'description' => 'This item should only appear in the archived scope.',
            'category' => 'Common Area',
            'priority' => 'Low',
            'status' => 'Pending',
        ]);
        $archived->delete();

        $catalog = app(HoaReportCatalog::class);
        $filters = $catalog->validateFilters('complaints', [
            'from' => '2026-10-18',
            'to' => '2026-10-18',
            'status' => 'Resolved',
            'q' => 'drainage',
            'sort' => 'ticket',
            'direction' => 'desc',
        ], true);

        $this->assertSame([$second->id, $first->id], $catalog->query('complaints', $filters)->pluck('complaints.id')->all());
        $archivedFilters = $catalog->validateFilters('complaints', ['scope' => 'archived', 'q' => 'archive'], true);
        $this->assertSame([$archived->id], $catalog->query('complaints', $archivedFilters)->pluck('id')->all());
    }

    /** @return array{User, Homeowner} */
    private function resident(string $firstName, string $ownership): array
    {
        $user = $this->userWithRole('homeowner');
        $user->forceFill(['first_name' => $firstName, 'last_name' => 'Resident'])->save();
        $homeowner = $user->homeowner()->create([
            'house_number' => (string) $user->id,
            'street' => 'Reporting Street',
            'block' => 'R',
            'lot' => (string) $user->id,
            'phase' => 'Southville Phase I',
            'residency_date' => '2020-01-01',
            'ownership_type' => $ownership,
            'status' => 'Active',
        ]);

        return [$user, $homeowner];
    }

    private function setting(): DuesSetting
    {
        return DuesSetting::query()->create([
            'name' => 'Reporting dues '.uniqid(),
            'amount' => '1000.10',
            'frequency' => 'Monthly',
            'due_day' => 31,
            'starts_on' => '2026-01-01',
            'is_active' => true,
        ]);
    }

    private function payment(int $homeownerId, int $settingId, int $recorderId, string $amount, string $reviewStatus, ?string $proof = null): Payment
    {
        return Payment::query()->create([
            'homeowner_id' => $homeownerId,
            'payer_homeowner_id' => $homeownerId,
            'dues_setting_id' => $settingId,
            'amount_paid' => $amount,
            'balance' => '0.00',
            'penalty' => '0.00',
            'payment_date' => '2026-10-18',
            'covered_period' => 'October 2026',
            'payment_method' => 'Cash',
            'status' => 'Paid',
            'proof_path' => $proof,
            'review_status' => $reviewStatus,
            'recorded_by' => $recorderId,
        ]);
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create(['account_status' => 'Active']);
        $user->assignRole($role);

        return $user;
    }
}
