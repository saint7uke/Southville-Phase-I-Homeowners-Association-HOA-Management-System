<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Exports\HoaReportExport;
use App\Models\ReportExport;
use App\Models\User;
use App\Services\Reporting\HoaReportCatalog;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Excel as ExcelFormat;
use Maatwebsite\Excel\Facades\Excel;
use RuntimeException;
use Throwable;

final class GenerateHoaReport implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 600;

    public bool $failOnTimeout = true;

    public function __construct(public readonly int $reportExportId) {}

    public function middleware(): array
    {
        return [(new WithoutOverlapping('hoa-report:'.$this->reportExportId))->releaseAfter(60)->expireAfter(660)];
    }

    public function handle(): void
    {
        $record = ReportExport::query()->findOrFail($this->reportExportId);
        if (in_array($record->status, ['Completed', 'Expired'], true)) {
            return;
        }
        $record->update(['status' => 'Processing', 'failure_reason' => null]);

        $format = in_array($record->format, ['csv', 'xlsx'], true) ? $record->format : 'csv';
        $filters = $record->filters ?? [];
        $path = sprintf('reports/%d/%d.%s', $record->user_id, $record->id, $format);

        try {
            $user = User::query()->findOrFail($record->user_id);
            $catalog = app(HoaReportCatalog::class);
            $catalog->authorize($user, $record->panel, $record->report, $format, (string) ($filters['scope'] ?? 'current'));
            $filters = $catalog->validateFilters($record->report, $filters, $record->panel === 'admin');
            $stored = Excel::store(
                new HoaReportExport($record->report, filters: $filters, generatedBy: $user->full_name),
                $path,
                'local',
                $format === 'csv' ? ExcelFormat::CSV : ExcelFormat::XLSX,
            );
            if (! $stored || ! Storage::disk('local')->exists($path)) {
                throw new RuntimeException('The report file could not be stored.');
            }

            $record->update([
                'status' => 'Completed',
                'file_path' => $path,
                'completed_at' => now(),
                'expires_at' => now()->addDays(7),
            ]);
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($path);
            $record->update([
                'status' => 'Failed',
                'failure_reason' => 'Report generation failed. Please retry or contact the administrator.',
            ]);

            throw $exception;
        }
    }

    public function failed(?Throwable $exception): void
    {
        $record = ReportExport::query()->find($this->reportExportId);
        if ($record === null || in_array($record->status, ['Completed', 'Expired'], true)) {
            return;
        }

        if ($record->file_path) {
            Storage::disk('local')->delete($record->file_path);
        }
        Storage::disk('local')->delete(sprintf('reports/%d/%d.csv', $record->user_id, $record->id));
        Storage::disk('local')->delete(sprintf('reports/%d/%d.xlsx', $record->user_id, $record->id));
        $record->update([
            'status' => 'Failed',
            'file_path' => null,
            'failure_reason' => 'Report generation failed. Please retry or contact the administrator.',
        ]);
    }
}
