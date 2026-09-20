<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\ReportExport;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

final class PruneExpiredReportExports extends Command
{
    protected $signature = 'hoa:prune-report-exports';

    protected $description = 'Delete expired private report files while retaining their audit metadata';

    public function handle(): int
    {
        $count = 0;

        ReportExport::query()
            ->whereNotNull('file_path')
            ->where('expires_at', '<=', now())
            ->chunkById(100, function ($exports) use (&$count): void {
                foreach ($exports as $export) {
                    Storage::disk('local')->delete($export->file_path);
                    $export->update(['status' => 'Expired', 'file_path' => null]);
                    $count++;
                }
            });

        $this->info("Pruned {$count} expired report export(s).");

        return self::SUCCESS;
    }
}
