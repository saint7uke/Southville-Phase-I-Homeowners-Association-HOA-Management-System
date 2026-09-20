<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Dues\FlagDelinquentHomeowners as FlagDelinquentHomeownersAction;
use Illuminate\Console\Command;

final class FlagDelinquentHomeowners extends Command
{
    protected $signature = 'hoa:flag-delinquent-homeowners';

    protected $description = 'Flag homeowners who have reached the configured consecutive overdue-month threshold.';

    public function handle(FlagDelinquentHomeownersAction $action): int
    {
        $this->info('Flagged '.$action->handle().' homeowner account(s) delinquent.');

        return self::SUCCESS;
    }
}
