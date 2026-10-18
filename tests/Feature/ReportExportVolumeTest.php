<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Exports\HoaReportExport;
use App\Filament\Pages\Reports;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Maatwebsite\Excel\Excel;
use Maatwebsite\Excel\Facades\Excel as Spreadsheet;
use Tests\TestCase;

final class ReportExportVolumeTest extends TestCase
{
    use RefreshDatabase;

    public function test_spreadsheet_export_includes_rows_beyond_the_first_query_chunk(): void
    {
        $this->fixture(1005);
        $csv = Spreadsheet::raw(new HoaReportExport('announcements'), Excel::CSV);
        $this->assertCount(1006, preg_split('/\r?\n/', trim($csv)));
        $this->assertStringContainsString('Volume announcement 0001', $csv);
        $this->assertStringContainsString('Volume announcement 1005', $csv);
    }

    public function test_pdf_limit_is_enforced_in_the_page_and_direct_download(): void
    {
        $admin = $this->fixture(2001);
        $this->actingAs($admin, 'admin');
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Livewire::test(Reports::class)->call('downloadReport', 'announcements', 'pdf')
            ->assertNotified('PDF row limit exceeded')->assertNoRedirect();
        $this->get(route('admin.reports.download', ['report' => 'announcements', 'format' => 'pdf']))
            ->assertStatus(422)->assertSee('2,000');
    }

    private function fixture(int $count): User
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('hoa_admin');
        $rows = [];
        foreach (range(1, $count) as $index) {
            $rows[] = [
                'title' => sprintf('Volume announcement %04d', $index), 'content' => 'Volume fixture.',
                'category' => 'General', 'audience' => 'Residents', 'status' => 'Draft',
                'created_by' => $admin->id, 'created_at' => now(), 'updated_at' => now(),
            ];
            if (count($rows) === 100) {
                DB::table('announcements')->insert($rows);
                $rows = [];
            }
        }
        if ($rows !== []) {
            DB::table('announcements')->insert($rows);
        }

        return $admin;
    }
}
