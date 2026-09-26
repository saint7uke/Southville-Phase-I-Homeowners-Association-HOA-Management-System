# Deployment Guide

## Local XAMPP

1. Start Apache and MySQL in the XAMPP Control Panel.
2. Create a MySQL database named `hoa_system` with `utf8mb4` collation.
3. Copy `.env.example` to `.env`, set the database and Gmail SMTP values, then generate the key.
4. Run Composer install, `php artisan migrate --seed`, and `npm.cmd run build`.
5. Start the application with `serve.bat`, the queue with `queue-worker.bat`, and the scheduler with `scheduler.bat` in separate terminals.
6. Open `http://127.0.0.1:8000`. Do not close the queue or scheduler windows while testing emails or automated dues.

The XAMPP directory contains a space, so use the checked-in batch launchers or quote the full PHP path. Do not rely on a global `php` command unless `F:\Xampp 8\php` has been added to `PATH`.

## Production release

The domain document root must point to the repository's `public` directory.

```bash
composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist
npm ci
npm run build
php artisan migrate --force
php artisan db:seed --class=RolesAndPermissionsSeeder --force
php artisan db:seed --class=AdminUserSeeder --force
php artisan optimize
php artisan queue:restart
```

Required production settings include `APP_ENV=production`, `APP_DEBUG=false`, HTTPS `APP_URL`, `APP_TIMEZONE=Asia/Manila`, `SESSION_SECURE_COOKIE=true`, MySQL 8 credentials, `QUEUE_CONNECTION=database` or Redis, and valid SMTP credentials. Do not copy demonstration accounts or test email addresses into production.

For a fresh production database, populate every `HOA_INITIAL_ADMIN_*` value in `.env` immediately before running `AdminUserSeeder`. The password and confirmation must match and satisfy the application password policy. The seeder creates an Admin only when that email is unused, is safe to repeat without rotating an existing Admin's credentials, and refuses to elevate an existing non-Admin account. After the first successful login, remove both password values from `.env`, run `php artisan config:clear`, and rebuild the configuration cache. Never run the general `DatabaseSeeder` in production because it contains demonstration identities.

Report jobs allow 600 seconds. Keep `DB_QUEUE_RETRY_AFTER=660` (or `REDIS_QUEUE_RETRY_AFTER=660` when using Redis) greater than that timeout so another worker does not receive a still-running report. Use a shared cache for job overlap locks when operating multiple workers.

Run one scheduler entry every minute:

```cron
* * * * * cd /path/to/hoa-system && php artisan schedule:run >> /dev/null 2>&1
```

Supervise the queue worker (Supervisor/systemd on a VPS). On shared hosting, run `queue:work --stop-when-empty --tries=3` from a one-minute cron while monitoring for overlap. Restart workers after every deployment.

## Release checks

Run `composer qa`, `npm run build`, `php artisan route:cache`, `php artisan view:cache`, `php artisan schedule:list`, and `composer audit`. Verify `/up` returns success with the production database available and fails in a controlled dependency-outage check, then verify all three panel logins, a queued test email, an authorized private download, and a database backup before switching traffic.

The checked-in `quality` GitHub Actions workflow includes an exact MySQL 8.4 job and runs on push, pull request, or approved manual dispatch. The current release line passed `sqlite-test`, `mysql-test`, and the Chromium, Firefox, and WebKit `browser-matrix` jobs in [run 36245505037](https://github.com/saint7uke/Southville-Phase-I-Homeowners-Association-HOA-Management-System/actions/runs/36245505037). Require those five jobs on the protected release branch before merge or deployment, and retain the exact candidate's successful run URL in the UAT record. A local MariaDB pass remains useful compatibility evidence but does not replace the native MySQL 8.4 job.

Do not switch traffic until the [UAT execution record](UAT_EXECUTION_RECORD.md) is accepted, protected-test changes are approved, production settings are signed off without recording secret values, and the named deployment approver authorizes the release window.
