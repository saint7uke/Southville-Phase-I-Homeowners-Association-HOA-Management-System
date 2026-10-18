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

final class PaymentExportController extends Controller
{
    public function __invoke(Request $request): BinaryFileResponse
    {
        $panel = $request->is('staff/*') ? 'staff' : 'admin';
        $report = $panel === 'staff' ? 'payment-period' : 'payment-history';
        $user = $request->user();
        abort_unless($user instanceof User, 403);
        $catalog = app(HoaReportCatalog::class);
        $catalog->authorize($user, $panel, $report, 'xlsx');
        $data = $catalog->validateFilters($report, $request->query(), false);

        AuditLog::query()->create([
            'user_id' => $user?->id,
            'panel' => $panel,
            'action' => 'Report.payments_exported',
            'new_values' => $data,
            'ip_address' => $request->ip(),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 500),
        ]);

        return Excel::download(new HoaReportExport($report, filters: $data, generatedBy: $user->full_name), 'payments-'.today()->toDateString().'.xlsx');
    }
}
