<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Exports\HoaReportExport;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

final class HoaReportController extends Controller
{
    public function __invoke(Request $request, string $report, ?string $format = null): BinaryFileResponse|Response|JsonResponse
    {
        abort_unless(isset(HoaReportExport::TITLES[$report]), 404);
        $isPreview = $format === null && ($request->route()->getName() ?? '')->endsWith('.preview');
        $isDownload = $format !== null;

        if ($isDownload) {
            abort_unless(in_array($format, HoaReportExport::FORMATS[$report], true), 404);
        }
        abort_if($request->is('staff/*') && $report === 'payment-history', 403);

        $data = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'status' => ['nullable', Rule::in(HoaReportExport::STATUS_FILTERS[$report] ?? [])],
        ]);
        $export = new HoaReportExport($report, $data['from'] ?? null, $data['to'] ?? null, $data['status'] ?? null);
        $panel = $request->is('staff/*') ? 'staff' : 'admin';

        if ($isPreview) {
            AuditLog::query()->create([
                'user_id' => $request->user()?->id,
                'panel' => $panel,
                'action' => 'Report.'.$report.'_previewed',
                'new_values' => $data,
                'ip_address' => $request->ip(),
                'user_agent' => mb_substr((string) $request->userAgent(), 0, 500),
            ]);

            $records = $export->query()->limit(10)->get();
            $rows = $records->map(fn ($record): array => $export->map($record))->all();

            return response()->json([
                'title' => $export->title(),
                'headings' => $export->headings(),
                'rows' => $rows,
                'hasMore' => $records->count() >= 10,
            ]);
        }

        AuditLog::query()->create([
            'user_id' => $request->user()?->id,
            'panel' => $panel,
            'action' => 'Report.'.$report.'_exported',
            'new_values' => [...$data, 'format' => $format],
            'ip_address' => $request->ip(),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 500),
        ]);

        $filename = $report.'-'.today()->toDateString().'.'.$format;
        if ($format === 'csv') {
            return Excel::download($export, $filename, \Maatwebsite\Excel\Excel::CSV);
        }

        $records = $export->query()->limit(2001)->get();
        abort_if($records->count() > 2000, 422, 'This PDF exceeds 2,000 rows. Narrow the date/status filters or export CSV for chunked processing.');
        $rows = $records->map(fn ($record): array => $export->map($record))->all();

        return Pdf::loadView('pdf.report', [
            'title' => $export->title(),
            'headings' => $export->headings(),
            'rows' => $rows,
            'filters' => $data,
            'generatedBy' => $request->user()?->full_name ?? 'System',
        ])->setPaper('a4', 'landscape')->download($filename);
    }
}
