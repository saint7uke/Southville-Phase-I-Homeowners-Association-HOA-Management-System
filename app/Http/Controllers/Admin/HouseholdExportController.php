<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Exports\HouseholdSummaryExport;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Household;
use App\Services\Exports\AdminExportLetterhead;
use App\Services\Exports\HouseholdExportData;
use App\Support\OperationalAccess;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Excel as ExcelFormat;
use Maatwebsite\Excel\Facades\Excel;

final class HouseholdExportController extends Controller
{
    public function __invoke(Request $request, Household $household, string $format)
    {
        $actor = $request->user('admin');
        OperationalAccess::authorize($actor, 'manage_homeowners', true);
        abort_unless(in_array($format, ['pdf', 'xlsx'], true) && ! $household->trashed(), 404);
        $data = app(HouseholdExportData::class)->prepare($household, true);
        $filename = 'hoa-household-'.$household->identifier.'.'.$format;

        $response = $format === 'xlsx'
            ? Excel::download(new HouseholdSummaryExport($data, $actor->full_name), $filename, ExcelFormat::XLSX)
            : Pdf::loadView('pdf.household', [...$data, 'letterhead' => app(AdminExportLetterhead::class)->dataUri()])->setPaper('a4')
                ->setOption(['isRemoteEnabled' => false, 'isPhpEnabled' => false])->download($filename);

        AuditLog::query()->create([
            'user_id' => $actor->id, 'panel' => 'admin', 'action' => 'Household.exported',
            'auditable_type' => Household::class, 'auditable_id' => $household->id,
            'new_values' => ['format' => $format], 'ip_address' => $request->ip(),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 500),
        ]);

        $response->headers->set('Cache-Control', 'private, no-store');

        return $response;
    }
}
