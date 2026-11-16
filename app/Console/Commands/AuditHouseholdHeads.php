<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Households\ManageHouseholdHead;
use App\Models\Household;
use Illuminate\Console\Command;

final class AuditHouseholdHeads extends Command
{
    protected $signature = 'hoa:audit-household-heads {--json : Print safe record IDs and issue categories as JSON}';

    protected $description = 'Read-only household head consistency report; never assigns or repairs records';

    public function handle(ManageHouseholdHead $heads): int
    {
        $issues = [];
        Household::query()->orderBy('id')->chunkById(100, function ($households) use ($heads, &$issues): void {
            foreach ($households as $household) {
                $status = $heads->status($household);
                if ($status['conflict'] || $status['head_name'] === null) {
                    $issues[] = ['household_id' => $household->id, 'head_member_id' => $household->head_member_id,
                        'current_head_label_ids' => $household->members()->whereNull('ended_at')->where('relationship', 'Head')->orderBy('id')->pluck('id')->all(),
                        'issue' => $status['conflict'] ? 'conflicting_assignment' : 'head_unassigned'];
                }
            }
        });
        if ($this->option('json')) {
            $this->line(json_encode($issues, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));
        } else {
            $this->table(['Household ID', 'Selected member ID', 'Current Head label IDs', 'Issue'], array_map(fn ($row) => [
                $row['household_id'], $row['head_member_id'] ?? '-', implode(', ', $row['current_head_label_ids']), $row['issue'],
            ], $issues));
            $this->info('Read-only audit complete. No household or member records were changed.');
        }

        return self::SUCCESS;
    }
}
