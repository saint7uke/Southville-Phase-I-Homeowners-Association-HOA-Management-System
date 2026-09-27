<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Pages\Reports;
use App\Jobs\GenerateHoaReport;
use App\Models\AuditLog;
use App\Models\Complaint;
use App\Models\DuesSetting;
use App\Models\Payment;
use App\Models\ReportExport;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Maatwebsite\Excel\Facades\Excel;
use RuntimeException;
use Tests\TestCase;

final class ReportAndAuditTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_admin_can_export_csv_pdf_and_audit_logs(): void
    {
        $admin = $this->userWithRole('hoa_admin');

        $this->actingAs($admin, 'admin')
            ->get(route('admin.reports.download', ['report' => 'homeowners', 'format' => 'csv']))
            ->assertOk()
            ->assertDownload('homeowners-'.today()->toDateString().'.csv');

        $this->actingAs($admin, 'admin')
            ->get(route('admin.reports.download', ['report' => 'complaints', 'format' => 'pdf']))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $this->actingAs($admin, 'admin')
            ->get(route('admin.reports.audit-logs'))
            ->assertOk();

        $this->assertDatabaseHas('audit_logs', ['user_id' => $admin->id, 'panel' => 'admin', 'action' => 'Report.homeowners_exported']);
        $this->assertDatabaseHas('audit_logs', ['user_id' => $admin->id, 'panel' => 'admin', 'action' => 'Report.audit_logs_exported']);
    }

    public function test_staff_has_limited_reports_and_homeowner_has_none(): void
    {
        $staff = $this->userWithRole('hoa_staff');
        $homeowner = $this->userWithRole('homeowner');

        $this->actingAs($staff, 'staff')
            ->get(route('staff.reports.download', ['report' => 'delinquency', 'format' => 'csv']))
            ->assertOk();
        $this->actingAs($staff, 'staff')
            ->get(route('staff.reports.download', ['report' => 'payment-history', 'format' => 'csv']))
            ->assertForbidden();
        $this->actingAs($homeowner, 'homeowner')
            ->get(route('admin.reports.download', ['report' => 'homeowners', 'format' => 'csv']))
            ->assertForbidden();
    }

    public function test_report_filters_are_validated_and_audit_log_is_read_only(): void
    {
        $admin = $this->userWithRole('hoa_admin');

        $this->actingAs($admin, 'admin')
            ->get(route('admin.reports.download', ['report' => 'requests', 'format' => 'csv', 'from' => '2026-09-10', 'to' => '2026-09-01']))
            ->assertSessionHasErrors('to');

        $log = AuditLog::query()->create(['user_id' => $admin->id, 'action' => 'Test.event']);
        $this->assertFalse($admin->can('update', $log));
        $this->assertFalse($admin->can('delete', $log));
        $this->assertFalse($admin->can('create', AuditLog::class));
    }

    public function test_report_centers_are_role_specific_and_formats_follow_the_matrix(): void
    {
        $admin = $this->userWithRole('hoa_admin');
        $staff = $this->userWithRole('hoa_staff');

        $this->actingAs($admin, 'admin')->get('/admin/reports')->assertOk()->assertSee('Full Payment Collection History')->assertSee('Queue CSV')->assertSee('All statuses');
        $this->actingAs($staff, 'staff')->get('/staff/reports')->assertOk()->assertDontSee('Full Payment Collection History');
        $this->actingAs($admin, 'admin')->get(route('admin.reports.download', ['report' => 'homeowners', 'format' => 'pdf']))->assertNotFound();
    }

    public function test_queued_csv_is_generated_privately_and_only_its_owner_can_download_it(): void
    {
        Storage::fake('local');
        $admin = $this->userWithRole('hoa_admin');
        $otherAdmin = $this->userWithRole('hoa_admin');
        $export = ReportExport::query()->create([
            'user_id' => $admin->id,
            'panel' => 'admin',
            'report' => 'homeowners',
            'format' => 'csv',
            'filters' => [],
            'status' => 'Pending',
        ]);

        (new GenerateHoaReport($export->id))->handle();
        $export->refresh();

        $this->assertSame('Completed', $export->status);
        Storage::disk('local')->assertExists($export->file_path);

        $this->actingAs($otherAdmin, 'admin')
            ->get(route('admin.reports.queued.download', $export))
            ->assertForbidden();
        $this->actingAs($admin, 'admin')
            ->get(route('admin.reports.queued.download', $export))
            ->assertOk()
            ->assertDownload('homeowners-'.$export->created_at->toDateString().'.csv');
    }

    public function test_report_pagination_sorting_and_page_size_work_through_livewire(): void
    {
        $this->actingAs($this->userWithRole('hoa_admin'), 'admin');
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $page = Livewire::test(Reports::class)
            ->set('perPage', 5)
            ->call('setPage', 2)
            ->assertSet('paginators.page', 2)
            ->assertSee('Showing 6–8 of 8 reports')
            ->call('sortBy', 'title')
            ->assertSet('sortDirection', 'desc')
            ->assertSet('paginators.page', 1)
            ->call('sortBy', 'unsupported')
            ->assertSet('sortColumn', 'title')
            ->assertSet('sortDirection', 'desc');

        $titles = $page->instance()->getReportsForCategory('all')->pluck('title')->all();
        $expected = $titles;
        rsort($expected);
        $this->assertSame($expected, $titles);

        $page->set('perPage', 0)->assertSet('perPage', 10)->assertSet('paginators.page', 1);
    }

    public function test_staff_cannot_download_a_previously_generated_admin_history_export(): void
    {
        Storage::fake('local');
        $user = $this->userWithRole('hoa_staff');
        Storage::disk('local')->put('reports/history.csv', 'private financial history');
        $export = ReportExport::query()->create([
            'user_id' => $user->id,
            'panel' => 'admin',
            'report' => 'payment-history',
            'status' => 'Completed',
            'file_path' => 'reports/history.csv',
            'expires_at' => now()->addDay(),
        ]);

        $this->actingAs($user, 'staff')->get(route('staff.reports.queued.download', $export))->assertForbidden();
    }

    public function test_report_actions_read_the_filters_bound_to_the_form(): void
    {
        Queue::fake();
        $admin = $this->userWithRole('hoa_admin');
        $this->actingAs($admin, 'admin');
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(Reports::class)
            ->set('filters.homeowners', ['from' => '2026-01-01', 'to' => '2026-09-01', 'status' => 'Active'])
            ->call('queueReport', 'homeowners')
            ->assertHasNoErrors()
            ->call('previewReport', 'homeowners')
            ->assertDispatched('report-preview', fn ($event, $parameters): bool => $parameters['title'] === 'Homeowner Master List');

        $export = ReportExport::query()->sole();
        $this->assertSame('Active', $export->filters['status']);
        $this->assertSame('2026-01-01', $export->filters['from']);
        Queue::assertPushed(GenerateHoaReport::class);
    }

    public function test_report_preview_only_claims_more_rows_when_an_eleventh_record_exists(): void
    {
        $admin = $this->userWithRole('hoa_admin');
        foreach (range(1, 10) as $index) {
            $resident = $this->userWithRole('homeowner');
            $resident->homeowner()->create([
                'house_number' => (string) $index,
                'street' => 'Preview Street',
                'block' => 'P',
                'lot' => (string) $index,
                'phase' => 'Southville Phase I',
                'residency_date' => '2020-01-01',
                'ownership_type' => 'Owner',
                'status' => 'Active',
            ]);
        }

        $this->actingAs($admin, 'admin');
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(Reports::class)
            ->call('previewReport', 'homeowners')
            ->assertDispatched('report-preview', fn ($event, $parameters): bool => count($parameters['rows']) === 10 && $parameters['hasMore'] === false);

        $eleventh = $this->userWithRole('homeowner');
        $eleventh->homeowner()->create([
            'house_number' => '11',
            'street' => 'Preview Street',
            'block' => 'P',
            'lot' => '11',
            'phase' => 'Southville Phase I',
            'residency_date' => '2020-01-01',
            'ownership_type' => 'Owner',
            'status' => 'Active',
        ]);

        Livewire::test(Reports::class)
            ->call('previewReport', 'homeowners')
            ->assertDispatched('report-preview', fn ($event, $parameters): bool => count($parameters['rows']) === 10 && $parameters['hasMore'] === true);
    }

    public function test_payment_history_retains_archived_homeowner_and_recorder_names(): void
    {
        $admin = $this->userWithRole('hoa_admin');
        $resident = $this->userWithRole('homeowner');
        $resident->forceFill(['first_name' => 'Archived', 'middle_name' => null, 'last_name' => 'Resident'])->save();
        $homeowner = $resident->homeowner()->create([
            'house_number' => '20',
            'street' => 'History Street',
            'block' => 'H',
            'lot' => '20',
            'phase' => 'Southville Phase I',
            'residency_date' => '2019-01-01',
            'ownership_type' => 'Owner',
            'status' => 'Inactive',
        ]);
        $recorder = $this->userWithRole('hoa_staff');
        $recorder->forceFill(['first_name' => 'Archived', 'middle_name' => null, 'last_name' => 'Recorder'])->save();
        $setting = DuesSetting::query()->create([
            'name' => 'Historical dues',
            'amount' => '500.00',
            'frequency' => 'Monthly',
            'is_active' => false,
        ]);
        Payment::query()->create([
            'homeowner_id' => $homeowner->id,
            'dues_setting_id' => $setting->id,
            'amount_paid' => '500.00',
            'balance' => '0.00',
            'penalty' => '0.00',
            'payment_date' => '2026-01-15',
            'covered_period' => 'January 2026',
            'payment_method' => 'Cash',
            'status' => 'Paid',
            'review_status' => 'Recorded',
            'recorded_by' => $recorder->id,
        ]);
        $homeowner->delete();
        $resident->delete();
        $recorder->delete();

        $response = $this->actingAs($admin, 'admin')
            ->get(route('admin.reports.download', ['report' => 'payment-history', 'format' => 'csv']))
            ->assertOk();

        $content = $response->streamedContent();
        $this->assertStringContainsString('Archived Resident', $content);
        $this->assertStringContainsString('Archived Recorder', $content);
    }

    public function test_csv_exports_neutralize_spreadsheet_formula_values(): void
    {
        $admin = $this->userWithRole('hoa_admin');
        $resident = $this->userWithRole('homeowner');
        $homeowner = $resident->homeowner()->create([
            'house_number' => '22',
            'street' => 'Safe Export Street',
            'block' => 'S',
            'lot' => '22',
            'phase' => 'Southville Phase I',
            'residency_date' => '2020-01-01',
            'ownership_type' => 'Owner',
            'status' => 'Active',
        ]);
        Complaint::query()->create([
            'homeowner_id' => $homeowner->id,
            'subject' => '=2+2',
            'description' => 'A formula-looking value must remain inert in spreadsheet software.',
            'category' => 'Others',
            'priority' => 'Low',
            'status' => 'Pending',
        ]);

        $response = $this->actingAs($admin, 'admin')
            ->get(route('admin.reports.download', ['report' => 'complaints', 'format' => 'csv']))
            ->assertOk();

        $content = $response->streamedContent();
        $this->assertStringContainsString("'=2+2", $content);
        $this->assertStringNotContainsString(',=2+2,', $content);
    }

    public function test_report_storage_failure_is_not_marked_complete_and_can_be_retried(): void
    {
        Storage::fake('local');
        $admin = $this->userWithRole('hoa_admin');
        $export = ReportExport::query()->create([
            'user_id' => $admin->id, 'panel' => 'admin', 'report' => 'homeowners', 'status' => 'Pending',
        ]);
        Excel::shouldReceive('store')->once()->andReturn(false);

        try {
            (new GenerateHoaReport($export->id))->handle();
            $this->fail('A failed storage result must throw for the queue retry.');
        } catch (RuntimeException) {
            $this->assertSame('Failed', $export->refresh()->status);
            $this->assertNull($export->file_path);
        }
    }

    public function test_completed_report_retry_preserves_the_original_file_and_expiry(): void
    {
        Storage::fake('local');
        $admin = $this->userWithRole('hoa_admin');
        $export = ReportExport::query()->create([
            'user_id' => $admin->id, 'panel' => 'admin', 'report' => 'homeowners', 'status' => 'Pending',
        ]);
        $job = new GenerateHoaReport($export->id);
        $job->handle();
        $original = $export->refresh()->only(['file_path', 'expires_at', 'completed_at']);
        $this->travel(1)->hours();
        $job->handle();
        $this->assertEquals($original, $export->refresh()->only(['file_path', 'expires_at', 'completed_at']));
        $this->assertCount(1, Storage::disk('local')->allFiles('reports'));
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create(['account_status' => 'Active']);
        $user->assignRole($role);

        return $user;
    }
}
