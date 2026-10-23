<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Exceptions\ExportLetterheadUnavailable;
use App\Exports\HoaReportExport;
use App\Filament\Pages\Households;
use App\Jobs\GenerateHoaReport;
use App\Models\DuesSetting;
use App\Models\Household;
use App\Models\ReportExport;
use App\Models\User;
use App\Services\Exports\AdminExportLetterhead;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Livewire\Livewire;
use Maatwebsite\Excel\Excel as ExcelFormat;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Tests\TestCase;

final class ExportLetterheadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        Notification::fake();
        Bus::fake();
        Storage::set('local', Storage::fake('export-letterhead-'.getmypid()));
    }

    public function test_artwork_is_a_checksum_verified_extract_of_the_preserved_original(): void
    {
        $path = app(AdminExportLetterhead::class)->imagePath();
        $manifest = json_decode(file_get_contents(dirname($path).'/manifest.json'), true);
        $this->assertSame(hash_file('sha256', resource_path('certificates/cohabitation-2026/original-unadjusted.docx')), $manifest['source_sha256']);
        $this->assertSame([4500, 834], array_slice(getimagesize($path), 0, 2));
        $this->assertSame([36.0, 28.0, 576.0, 128.0], $manifest['crop_points']);
        $this->assertSame(600, $manifest['dpi']);
        $this->assertSame($manifest['sha256'], hash_file('sha256', $path));
    }

    public function test_admin_report_pdf_includes_letterhead_but_staff_pdf_does_not(): void
    {
        $response = $this->actingAs($this->actor('hoa_admin'), 'admin')
            ->get(route('admin.reports.download', ['report' => 'complaints', 'format' => 'pdf']))->assertOk();
        $this->assertLetterheadPdf($response->getContent());
        auth('admin')->logout();
        $response = $this->actingAs($this->actor('hoa_staff'), 'staff')
            ->get(route('staff.reports.download', ['report' => 'complaints', 'format' => 'pdf', 'withAdminLetterhead' => true]))->assertOk();
        $this->assertStringNotContainsString('/Width 4500', $response->getContent());
    }

    public function test_admin_xlsx_keeps_the_same_filtered_sorted_rows_and_summary_properties(): void
    {
        $actor = $this->actor('hoa_admin');
        foreach (['Zulu assessment', 'Alpha assessment', 'Other fee'] as $name) {
            DuesSetting::create(['name' => $name, 'amount' => '489.25', 'frequency' => 'Monthly', 'due_day' => 31, 'starts_on' => '2026-10-01', 'is_active' => true, 'created_by' => $actor->id]);
        }
        $filters = ['q' => 'assessment', 'sort' => 'name', 'direction' => 'asc'];
        $response = $this->actingAs($actor, 'admin')->get(route('admin.reports.download', ['report' => 'dues-settings', 'format' => 'xlsx', ...$filters]))->assertOk();
        $book = $this->workbook($response);
        $sheet = $book->getActiveSheet();
        $plainPath = 'plain.xlsx';
        Excel::store(new HoaReportExport('dues-settings', filters: $filters), $plainPath, 'local', ExcelFormat::XLSX);
        $plain = IOFactory::load(Storage::disk('local')->path($plainPath))->getActiveSheet();
        $this->assertSame($plain->toArray(), $sheet->rangeToArray('A11:'.$sheet->getHighestDataColumn().$sheet->getHighestDataRow()));
        $this->assertCount(1, $sheet->getDrawingCollection());
        $this->assertSame('Dues Schedule Register', $sheet->getCell('A8')->getValue());
        $this->assertSame(['11', '11'], $sheet->getPageSetup()->getRowsToRepeatAtTop());
        $this->assertStringStartsWith('A1:', $sheet->getPageSetup()->getPrintArea());
        $this->assertStringContainsString('assessment', $book->getProperties()->getDescription());
        $this->assertSame($actor->full_name, $book->getProperties()->getCreator());
    }

    public function test_csv_previews_staff_xlsx_and_legacy_exports_remain_unbranded(): void
    {
        $admin = $this->actor('hoa_admin');
        $this->actingAs($admin, 'admin');
        $csv = $this->get(route('admin.reports.download', ['report' => 'complaints', 'format' => 'csv', 'withAdminLetterhead' => true]))->assertOk();
        $this->assertStringNotContainsString('SOUTHVILLE HOMEOWNERS ASSOCIATION INC.', file_get_contents($csv->baseResponse->getFile()->getPathname()));
        $preview = $this->get(route('admin.reports.preview', ['report' => 'complaints']))->assertOk();
        $this->assertArrayNotHasKey('letterhead', $preview->json());
        $legacy = $this->get(route('admin.reports.payments'))->assertOk();
        $this->assertCount(0, $this->workbook($legacy)->getActiveSheet()->getDrawingCollection());
        $this->assertSame('Liable Resident', $this->workbook($legacy)->getActiveSheet()->getCell('B1')->getValue());
        auth('admin')->logout();
        $staff = $this->actingAs($this->actor('hoa_staff'), 'staff')
            ->get(route('staff.reports.download', ['report' => 'dues-settings', 'format' => 'xlsx', 'withAdminLetterhead' => true]))->assertOk();
        $sheet = $this->workbook($staff)->getActiveSheet();
        $this->assertCount(0, $sheet->getDrawingCollection());
        $this->assertNotEmpty($sheet->getCell('A1')->getValue());
    }

    public function test_queued_admin_xlsx_has_letterhead_while_staff_xlsx_and_csv_do_not(): void
    {
        foreach ([['admin', 'hoa_admin', 'xlsx', 1], ['staff', 'hoa_staff', 'xlsx', 0], ['admin', 'hoa_admin', 'csv', 0]] as [$panel, $role, $format, $drawings]) {
            $export = ReportExport::create(['user_id' => $this->actor($role)->id, 'panel' => $panel, 'report' => 'complaints', 'format' => $format, 'filters' => [], 'status' => 'Pending']);
            (new GenerateHoaReport($export->id))->handle();
            $this->assertSame('Completed', $export->fresh()->status);
            $sheet = IOFactory::load(Storage::disk('local')->path($export->fresh()->file_path))->getActiveSheet();
            $this->assertCount($drawings, $sheet->getDrawingCollection());
        }
    }

    public function test_already_completed_queued_files_are_not_rewritten(): void
    {
        Storage::disk('local')->put('reports/existing.xlsx', 'historical saved bytes');
        $export = ReportExport::create(['user_id' => $this->actor('hoa_admin')->id, 'panel' => 'admin', 'report' => 'complaints', 'format' => 'xlsx', 'filters' => [], 'status' => 'Completed', 'file_path' => 'reports/existing.xlsx']);
        (new GenerateHoaReport($export->id))->handle();
        $this->assertSame('historical saved bytes', Storage::disk('local')->get('reports/existing.xlsx'));
    }

    public function test_admin_household_pdf_and_excel_share_current_member_data(): void
    {
        $household = $this->household();
        $household->members()->create(['full_name' => 'Ána Reyes', 'relationship' => 'Head', 'date_of_birth' => '1990-04-03', 'sex' => 'Female', 'residency_status' => 'Resident']);
        $household->members()->create(['full_name' => 'Former Member', 'relationship' => 'Other', 'ended_at' => now()]);
        $household->members()->create(['full_name' => 'Archived Member', 'relationship' => 'Other'])->delete();
        $this->actingAs($this->actor('hoa_admin'), 'admin');
        $pdf = $this->get($this->householdUrl($household, 'pdf'))->assertOk()->assertDownload('hoa-household-'.$household->identifier.'.pdf');
        $this->assertLetterheadPdf($pdf->getContent());
        $excel = $this->get($this->householdUrl($household, 'xlsx'))->assertOk()->assertDownload('hoa-household-'.$household->identifier.'.xlsx');
        $sheet = $this->workbook($excel)->getActiveSheet();
        $this->assertSame(['Ána Reyes', 'Head', 'Resident', '1990-04-03', 'Female'], $sheet->rangeToArray('A12:E12')[0]);
        $this->assertSame(12, $sheet->getHighestDataRow());
        $this->assertCount(1, $sheet->getDrawingCollection());
        $this->assertStringContainsString('PROVISIONAL RBI', $sheet->getCell('A9')->getValue());
        $this->assertStringContainsString('Current members: 1', $sheet->getCell('A9')->getValue());
        $this->assertDatabaseHas('audit_logs', ['action' => 'Household.exported', 'panel' => 'admin', 'auditable_id' => $household->id]);
        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseCount('dues_obligations', 0);
        $this->assertDatabaseCount('certificates', 0);
    }

    public function test_household_excel_keeps_formula_like_names_as_literal_text(): void
    {
        $household = $this->household();
        $household->members()->create(['full_name' => '=HYPERLINK("https://example.invalid","test")', 'relationship' => 'Other']);
        $response = $this->actingAs($this->actor('hoa_admin'), 'admin')->get($this->householdUrl($household, 'xlsx'))->assertOk();
        $cell = $this->workbook($response)->getActiveSheet()->getCell('A12');
        $this->assertSame(DataType::TYPE_STRING, $cell->getDataType());
        $this->assertStringStartsWith('=HYPERLINK', $cell->getValue());
    }

    public function test_empty_household_exports_and_long_unicode_fields_are_supported(): void
    {
        $household = $this->household();
        $this->actingAs($this->actor('hoa_admin'), 'admin');
        $empty = $this->get($this->householdUrl($household, 'xlsx'))->assertOk();
        $sheet = $this->workbook($empty)->getActiveSheet();
        $this->assertSame(11, $sheet->getHighestDataRow());
        $this->assertStringContainsString('Current members: 0', $sheet->getCell('A9')->getValue());
        $name = str_repeat('María ', 35);
        $household->members()->create(['full_name' => $name, 'relationship' => 'Other']);
        $this->get($this->householdUrl($household, 'pdf'))->assertOk();
        $sheet = $this->workbook($this->get($this->householdUrl($household, 'xlsx'))->assertOk())->getActiveSheet();
        $this->assertSame($name, $sheet->getCell('A12')->getValue());
        $this->assertTrue($sheet->getStyle('A12')->getAlignment()->getWrapText());
        $this->assertGreaterThanOrEqual(48, $sheet->getRowDimension(9)->getRowHeight());
    }

    public function test_staff_and_homeowners_cannot_use_admin_household_export_routes(): void
    {
        $household = $this->household();
        foreach (['hoa_staff' => 'staff', 'homeowner' => 'homeowner'] as $role => $guard) {
            $this->actingAs($this->actor($role), $guard)->get($this->householdUrl($household, 'xlsx'))->assertRedirect();
            auth($guard)->logout();
        }
        $this->actingAs($this->actor('hoa_admin'), 'admin');
        $this->get($this->householdUrl($household, 'csv'))->assertNotFound();
        $household->delete();
        $this->get($this->householdUrl($household, 'pdf'))->assertNotFound();
    }

    public function test_inactive_admin_and_revoked_household_permission_are_denied(): void
    {
        $household = $this->household();
        $admin = $this->actor('hoa_admin');
        DB::table('users')->where('id', $admin->id)->update(['account_status' => 'Suspended']);
        $this->actingAs($admin->fresh(), 'admin')->get($this->householdUrl($household, 'pdf'))->assertForbidden();
        DB::table('users')->where('id', $admin->id)->update(['account_status' => 'Active']);
        $admin->roles->first()->revokePermissionTo('manage_homeowners');
        $this->actingAs($admin->fresh(), 'admin')->get($this->householdUrl($household, 'pdf'))->assertForbidden();
    }

    public function test_shared_household_pdf_remains_unbranded_for_managers_and_homeowners(): void
    {
        $resident = $this->actor('homeowner');
        $resident->homeowner()->create(['house_number' => '7', 'street' => 'Sample Street', 'block' => '1', 'lot' => '2', 'phase' => 'Southville Phase I', 'phase_suffix' => 'A', 'ownership_type' => 'Owner', 'residency_date' => '2020-01-01', 'status' => 'Active']);
        $household = Household::firstOrFail();
        foreach ([[$this->actor('hoa_admin'), 'admin'], [$this->actor('hoa_staff'), 'staff'], [$resident, 'homeowner']] as [$actor, $guard]) {
            $response = $this->actingAs($actor, $guard)->get(route('households.pdf', $household))->assertOk();
            $this->assertStringNotContainsString('/Width 4500', $response->getContent());
            auth($guard)->logout();
        }
    }

    public function test_admin_household_actions_use_scoped_routes_and_staff_actions_do_not(): void
    {
        $household = $this->household();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($this->actor('hoa_admin'), 'admin');
        Livewire::test(Households::class)->assertTableActionHasUrl('pdf', $this->householdUrl($household, 'pdf'), $household)
            ->assertTableActionVisible('excel', $household)->assertTableActionHasUrl('excel', $this->householdUrl($household, 'xlsx'), $household);
        auth('admin')->logout();
        Filament::setCurrentPanel(Filament::getPanel('staff'));
        $this->actingAs($this->actor('hoa_staff'), 'staff');
        Livewire::test(Households::class)->assertTableActionHasUrl('pdf', route('households.pdf', $household), $household)->assertTableActionHidden('excel', $household);
    }

    public function test_missing_artwork_has_readable_errors_without_breaking_unbranded_exports(): void
    {
        config(['exports.letterhead_directory' => storage_path('framework/testing/nonexistent-letterhead')]);
        $this->actingAs($this->actor('hoa_admin'), 'admin');
        foreach (['pdf', 'xlsx'] as $format) {
            $this->get(route('admin.reports.download', ['report' => 'complaints', 'format' => $format]))->assertStatus(503)
                ->assertSee('The export letterhead is unavailable')->assertSee('/admin/reports');
            $this->get($this->householdUrl($this->household(), $format))->assertStatus(503)->assertSee('/admin/households');
        }
        // The real five-per-minute report throttle counts failed exports too.
        $this->travel(61)->seconds();
        $this->get(route('admin.reports.download', ['report' => 'complaints', 'format' => 'csv']))->assertOk();
        $this->get(route('admin.reports.payments'))->assertOk();
        $this->get(route('admin.reports.preview', ['report' => 'complaints']))->assertOk();
    }

    public function test_corrupt_artwork_is_rejected_and_queued_failure_stores_no_file(): void
    {
        $real = config('exports.letterhead_directory');
        Storage::disk('local')->put('broken-header/manifest.json', file_get_contents($real.'/manifest.json'));
        Storage::disk('local')->put('broken-header/letterhead.png', 'corrupt data');
        config(['exports.letterhead_directory' => Storage::disk('local')->path('broken-header')]);
        $export = ReportExport::create(['user_id' => $this->actor('hoa_admin')->id, 'panel' => 'admin', 'report' => 'complaints', 'format' => 'xlsx', 'filters' => [], 'status' => 'Pending']);
        try {
            (new GenerateHoaReport($export->id))->handle();
            $this->fail('Corrupted artwork must not silently fall back.');
        } catch (ExportLetterheadUnavailable) {
            $this->assertSame('Failed', $export->fresh()->status);
            $this->assertNull($export->fresh()->file_path);
            Storage::disk('local')->assertMissing('reports/'.$export->user_id.'/'.$export->id.'.xlsx');
        }
    }

    public function test_multi_page_review_samples_keep_all_rows_and_printable_spreadsheet_areas(): void
    {
        $admin = $this->actor('hoa_admin');
        $household = $this->household();
        foreach (range(1, 85) as $number) {
            $household->members()->create(['full_name' => 'Review Resident '.$number, 'relationship' => 'Other', 'date_of_birth' => '1990-04-03', 'residency_status' => 'Resident']);
        }
        foreach (range(1, 65) as $number) {
            DuesSetting::create(['name' => 'Review assessment '.str_pad((string) $number, 3, '0', STR_PAD_LEFT), 'amount' => '489.25', 'frequency' => 'Monthly', 'due_day' => 31, 'starts_on' => '2026-10-01', 'is_active' => true, 'created_by' => $admin->id]);
        }
        $this->actingAs($admin, 'admin');
        $review = Storage::build(['driver' => 'local', 'root' => storage_path('framework/testing/export-letterhead-20261023/review')]);
        foreach (['pdf', 'xlsx'] as $format) {
            $householdResponse = $this->get($this->householdUrl($household, $format))->assertOk();
            $reportResponse = $this->get(route('admin.reports.download', ['report' => 'dues-settings', 'format' => $format]))->assertOk();
            foreach (['household' => $householdResponse, 'report' => $reportResponse] as $name => $response) {
                $review->put($name.'.'.$format, $format === 'pdf' ? $response->getContent() : file_get_contents($response->baseResponse->getFile()->getPathname()));
            }
        }
        $sheet = IOFactory::load($review->path('household.xlsx'))->getActiveSheet();
        $this->assertSame(96, $sheet->getHighestDataRow());
        $this->assertSame('Review Resident 85', $sheet->getCell('A96')->getValue());
        $this->assertSame('A1:E96', $sheet->getPageSetup()->getPrintArea());
        $this->assertCount(1, $sheet->getDrawingCollection());
        $sheet = IOFactory::load($review->path('report.xlsx'))->getActiveSheet();
        $this->assertSame(76, $sheet->getHighestDataRow());
        $this->assertSame('Review assessment 065', $sheet->getCell('A76')->getValue());
    }

    private function actor(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function household(): Household
    {
        $id = (string) Str::uuid();

        return Household::create(['identifier' => $id, 'address_key' => hash('sha256', $id), 'house_number' => '7', 'street' => 'Example Street', 'block' => '1', 'lot' => (string) (Household::withTrashed()->count() + 1), 'phase' => 'Southville Phase I', 'phase_suffix' => 'A']);
    }

    private function householdUrl(Household $household, string $format): string
    {
        return route('admin.households.export', ['household' => $household, 'format' => $format]);
    }

    private function workbook(TestResponse $response): Spreadsheet
    {
        return IOFactory::load($response->baseResponse->getFile()->getPathname());
    }

    private function assertLetterheadPdf(string $bytes): void
    {
        $this->assertStringStartsWith('%PDF-', $bytes);
        $this->assertStringContainsString('/Width 4500', $bytes);
        $this->assertStringContainsString('/Height 834', $bytes);
    }
}
