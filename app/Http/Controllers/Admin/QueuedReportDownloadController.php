<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\ReportExport;
use App\Models\User;
use App\Services\Reporting\HoaReportCatalog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

final class QueuedReportDownloadController extends Controller
{
    public function __invoke(Request $request, ReportExport $reportExport): BinaryFileResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User && $reportExport->user_id === $user->id, 403);
        $panel = $request->is('staff/*') ? 'staff' : 'admin';
        abort_unless($reportExport->panel === $panel, 403);
        app(HoaReportCatalog::class)->authorize($user, $panel, $reportExport->report, $reportExport->format, (string) (($reportExport->filters ?? [])['scope'] ?? 'current'));
        abort_unless($reportExport->status === 'Completed' && $reportExport->file_path, 404);
        abort_if($reportExport->expires_at?->isPast(), 410, 'This generated report has expired.');
        abort_unless(Storage::disk('local')->exists($reportExport->file_path), 404);

        AuditLog::query()->create([
            'user_id' => $request->user()?->id,
            'panel' => $reportExport->panel,
            'action' => 'Report.queued_export_downloaded',
            'new_values' => ['report_export_id' => $reportExport->id, 'report' => $reportExport->report],
            'ip_address' => $request->ip(),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 500),
        ]);

        return response()->download(
            Storage::disk('local')->path($reportExport->file_path),
            $reportExport->report.'-'.$reportExport->created_at->toDateString().'.'.$reportExport->format,
            ['Content-Type' => $reportExport->format === 'xlsx' ? 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' : 'text/csv; charset=UTF-8', 'X-Content-Type-Options' => 'nosniff'],
        );
    }
}
