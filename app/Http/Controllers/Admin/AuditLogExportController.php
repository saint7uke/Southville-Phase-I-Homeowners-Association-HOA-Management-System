<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Exports\AuditLogsExport;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

final class AuditLogExportController extends Controller
{
    public function __invoke(Request $request): BinaryFileResponse
    {
        $data = $request->validate(['from' => ['nullable', 'date'], 'to' => ['nullable', 'date', 'after_or_equal:from']]);

        AuditLog::query()->create([
            'user_id' => $request->user()?->id,
            'panel' => 'admin',
            'action' => 'Report.audit_logs_exported',
            'new_values' => $data,
            'ip_address' => $request->ip(),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 500),
        ]);

        return Excel::download(new AuditLogsExport($data['from'] ?? null, $data['to'] ?? null), 'audit-logs-'.today()->toDateString().'.csv', \Maatwebsite\Excel\Excel::CSV);
    }
}
