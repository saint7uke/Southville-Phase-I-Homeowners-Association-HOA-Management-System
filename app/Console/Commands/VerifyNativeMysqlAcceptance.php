<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Support\NativeMysqlAcceptance;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

final class VerifyNativeMysqlAcceptance extends Command
{
    protected $signature = 'hoa:verify-native-mysql';

    protected $description = 'Read-only preflight for an explicitly disposable native MySQL 8.4 acceptance database';

    public function handle(): int
    {
        try {
            $connection = (string) config('database.default');
            NativeMysqlAcceptance::assertDisposable(
                $connection,
                (string) config("database.connections.{$connection}.database"),
                filter_var(env('HOA_ALLOW_DESTRUCTIVE_TEST_DATABASE', false), FILTER_VALIDATE_BOOL),
            );
            $server = DB::selectOne('SELECT VERSION() AS version, DATABASE() AS database_name');
            NativeMysqlAcceptance::assertDisposable($connection, (string) $server->database_name, true);
            NativeMysqlAcceptance::assertVersion((string) $server->version);
            $this->info('Native MySQL 8.4 disposable-database preflight passed.');
            $this->line('Server version: '.preg_replace('/[^a-zA-Z0-9.\-]/', '', (string) $server->version));
            $this->comment('This checks identity only; acceptance still requires passing migration and workflow tests.');

            return self::SUCCESS;
        } catch (\DomainException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        } catch (Throwable) {
            $this->error('Could not verify the disposable database connection. Check test configuration privately.');

            return self::FAILURE;
        }
    }
}
