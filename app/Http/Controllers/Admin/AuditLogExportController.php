<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Exports\HoaReportExport;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\Reporting\HoaReportCatalog;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

final class AuditLogExportController extends Controller
{
    public function __invoke(Request $request): BinaryFileResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);
        $catalog = app(HoaReportCatalog::class);
        $catalog->authorize($user, 'admin', 'audit-activity', 'csv');
        $data = $catalog->validateFilters('audit-activity', $request->query(), false);

        AuditLog::query()->create([
            'user_id' => $request->user()?->id,
            'panel' => 'admin',
            'action' => 'Report.audit_logs_exported',
            'new_values' => $data,
            'ip_address' => $request->ip(),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 500),
        ]);

        return Excel::download(new HoaReportExport('audit-activity', filters: $data, generatedBy: $user->full_name), 'audit-logs-'.today()->toDateString().'.csv', \Maatwebsite\Excel\Excel::CSV);
    }
}
