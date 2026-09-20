<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Dues\MarkOverdueObligations;
use Illuminate\Console\Command;

final class MarkOverdueDues extends Command
{
    protected $signature = 'hoa:mark-overdue-dues';

    protected $description = 'Mark unpaid dues obligations past their due date as overdue.';

    public function handle(MarkOverdueObligations $marker): int
    {
        $this->info('Marked '.$marker->handle().' dues obligation(s) overdue.');

        return self::SUCCESS;
    }
}
