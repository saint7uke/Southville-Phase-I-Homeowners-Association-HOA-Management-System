<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Exports\HoaReportExport;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\Exports\AdminExportLetterhead;
use App\Services\Reporting\HoaReportCatalog;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Excel as ExcelFormat;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

final class HoaReportController extends Controller
{
    public function __invoke(Request $request, string $report, ?string $format = null): BinaryFileResponse|Response|JsonResponse
    {
        $catalog = app(HoaReportCatalog::class);
        abort_unless($catalog->exists($report), 404);
        $panel = $request->is('staff/*') ? 'staff' : 'admin';
        $user = $request->user();
        abort_unless($user instanceof User, 403);
        $isPreview = $format === null && str_ends_with((string) $request->route()?->getName(), '.preview');
        $catalog->authorize($user, $panel, $report, $isPreview ? null : $format, (string) $request->query('scope', 'current'));
        $filters = $catalog->validateFilters($report, $request->query(), $panel === 'admin');
        $withLetterhead = $request->routeIs('admin.reports.download') && in_array($format, ['pdf', 'xlsx'], true);
        $export = new HoaReportExport($report, filters: $filters, generatedBy: $user->full_name, withAdminLetterhead: $withLetterhead);

        if ($isPreview) {
            $records = $export->query()->limit(11)->get();
            $this->audit($request, $panel, $report, 'previewed', $filters);

            return response()->json([
                'title' => $export->title(),
                'headings' => $export->headings(),
                'rows' => $records->take(10)->map(fn ($record): array => $export->map($record))->all(),
                'hasMore' => $records->count() > 10,
                'summary' => $catalog->summary($report, $filters),
            ]);
        }

        $filename = $report.'-'.today()->toDateString().'.'.$format;
        if (in_array($format, ['csv', 'xlsx'], true)) {
            $response = Excel::download($export, $filename, $format === 'csv' ? ExcelFormat::CSV : ExcelFormat::XLSX);
            $this->audit($request, $panel, $report, 'exported', [...$filters, 'format' => $format]);

            return $response;
        }

        $records = $export->query()->limit(2001)->get();
        abort_if($records->count() > 2000, 422, 'This PDF exceeds 2,000 rows. Narrow the filters or use a queued CSV/XLSX export.');

        $response = Pdf::loadView('pdf.report', [
            'letterhead' => $withLetterhead ? app(AdminExportLetterhead::class)->dataUri() : null,
            'title' => $export->title(),
            'headings' => $export->headings(),
            'rows' => $records->map(fn ($record): array => $export->map($record))->all(),
            'filters' => $filters,
            'summary' => $catalog->summary($report, $filters),
            'generatedBy' => $user->full_name,
        ])->setPaper('a4', 'landscape')->download($filename);
        $this->audit($request, $panel, $report, 'exported', [...$filters, 'format' => $format]);

        return $response;
    }

    /** @param array<string, mixed> $values */
    private function audit(Request $request, string $panel, string $report, string $action, array $values): void
    {
        AuditLog::query()->create([
            'user_id' => $request->user()?->id,
            'panel' => $panel,
            'action' => 'Report.'.$report.'_'.$action,
            'new_values' => $values,
            'ip_address' => $request->ip(),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 500),
        ]);
    }
}
