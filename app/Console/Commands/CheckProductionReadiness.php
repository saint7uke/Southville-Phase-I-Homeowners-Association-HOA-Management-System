<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;

final class CheckProductionReadiness extends Command
{
    protected $signature = 'hoa:release-check {--json : Output checks as JSON}';

    protected $description = 'Fail closed when production configuration or required operational attestations are incomplete';

    public function handle(): int
    {
        $url = (string) config('app.url');
        $disk = (string) config('filesystems.default');
        $checks = [
            'production_environment' => app()->environment('production'),
            'debug_disabled' => config('app.debug') === false,
            'https_application_url' => str_starts_with(strtolower($url), 'https://'),
            'application_key_present' => filled(config('app.key')),
            'secure_session_cookie' => config('session.secure') === true,
            'http_only_session_cookie' => config('session.http_only') === true,
            'safe_same_site_cookie' => in_array(config('session.same_site'), ['lax', 'strict'], true),
            'persistent_queue' => ! in_array(config('queue.default'), ['sync', 'null', 'deferred', 'background'], true),
            'delivery_mailer' => ! in_array(config('mail.default'), ['log', 'array'], true),
            'private_default_storage' => $disk !== 'public' && (bool) data_get(config("filesystems.disks.{$disk}"), 'serve', false) === false,
            'mysql_database' => config('database.default') === 'mysql',
            'queue_worker_verified' => (bool) config('hoa.release.queue_worker_verified'),
            'scheduler_verified' => (bool) config('hoa.release.scheduler_verified'),
            'backup_restore_verified' => (bool) config('hoa.release.backup_restore_verified'),
            'mail_delivery_verified' => (bool) config('hoa.release.mail_delivery_verified'),
            'private_storage_verified' => (bool) config('hoa.release.private_storage_verified'),
            'security_incident_closed' => (bool) config('hoa.release.security_incident_closed'),
        ];

        $result = ['ready' => ! in_array(false, $checks, true), 'checks' => $checks];
        if ($this->option('json')) {
            $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
        } else {
            $this->table(['Check', 'Result'], collect($checks)->map(fn (bool $passed, string $name): array => [$name, $passed ? 'PASS' : 'FAIL'])->values()->all());
            $result['ready'] ? $this->info('Production release gate passed.') : $this->error('Production release gate failed. Resolve every failed check before deployment.');
        }

        return $result['ready'] ? self::SUCCESS : self::FAILURE;
    }
}
