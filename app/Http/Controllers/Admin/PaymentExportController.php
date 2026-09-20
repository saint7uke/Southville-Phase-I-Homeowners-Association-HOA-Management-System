<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Exports\PaymentsExport;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

final class PaymentExportController extends Controller
{
    public function __invoke(Request $request): BinaryFileResponse
    {
        $data = $request->validate(['from' => ['nullable', 'date'], 'to' => ['nullable', 'date', 'after_or_equal:from'], 'status' => ['nullable', 'in:Paid,Partial,Overdue']]);
        $user = $request->user();

        AuditLog::query()->create([
            'user_id' => $user?->id,
            'panel' => $request->is('staff/*') ? 'staff' : 'admin',
            'action' => 'Report.payments_exported',
            'new_values' => $data,
            'ip_address' => $request->ip(),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 500),
        ]);

        return Excel::download(new PaymentsExport($data['from'] ?? null, $data['to'] ?? null, $data['status'] ?? null), 'payments-'.today()->toDateString().'.xlsx');
    }
}
