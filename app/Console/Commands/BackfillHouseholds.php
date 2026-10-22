<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Homeowner;
use App\Services\HouseholdDirectory;
use Illuminate\Console\Command;

final class BackfillHouseholds extends Command
{
    protected $signature = 'hoa:backfill-households {--apply : Apply reviewed links; default is a read-only dry run}';

    protected $description = 'Review and optionally backfill property household memberships without merging residents';

    public function handle(HouseholdDirectory $directory): int
    {
        $review = [];
        $eligible = 0;
        Homeowner::query()->with('user')->orderBy('id')->chunkById(200, function ($rows) use ($directory, &$review, &$eligible): void {
            foreach ($rows as $resident) {
                if ($reason = $directory->reviewReason($resident)) {
                    $review[] = [$resident->id, $reason];

                    continue;
                }
                $eligible++;
                if ($this->option('apply')) {
                    $directory->synchronize($resident);
                }
            }
        });
        $this->table(['Resident record ID', 'Review reason'], $review);
        $this->info(($this->option('apply') ? 'Applied eligible links: ' : 'Dry-run eligible records: ').$eligible);
        $this->line('No account, financial ledger, or historical address records were merged or rewritten.');

        return self::SUCCESS;
    }
}
