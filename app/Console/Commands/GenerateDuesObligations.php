<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Dues\GenerateMonthlyObligations;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

final class GenerateDuesObligations extends Command
{
    protected $signature = 'hoa:generate-dues {--month= : Month to generate in YYYY-MM format}';

    protected $description = 'Generate missing monthly HOA dues obligations idempotently.';

    public function handle(GenerateMonthlyObligations $generator): int
    {
        $month = $this->option('month');
        $period = is_string($month) && $month !== ''
            ? CarbonImmutable::createFromFormat('!Y-m', $month)->startOfMonth()
            : null;

        if ($period === false) {
            $this->error('The --month option must use YYYY-MM.');

            return self::INVALID;
        }

        $this->info('Created '.$generator->handle($period).' dues obligation(s).');

        return self::SUCCESS;
    }
}
