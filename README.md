# Southville Phase I HOA Information System

A Laravel 12 information system for Southville Phase I, Brgy. Inocencio, Trece Martires City, Cavite. It provides independent Filament panels for administrators, staff, and homeowners; a React public landing page; dues and payment-proof workflows; private attachments and certificates; queued notifications; audit history; dashboards; and authorized CSV/PDF reports.

## Stack

- Laravel 12.69 and PHP 8.2+
- Filament 4.13 Admin, Staff, and Homeowner panels
- Spatie Laravel Permission
- React 19, Vite 7, Tailwind CSS 4
- Laravel Excel
- MySQL 8 for local/production data; isolated SQLite in-memory tests

The original brief named Filament 3 and Tailwind 3. This implementation uses their current Laravel 12-compatible major versions while preserving the specified workflows.

PHPUnit sets a dedicated, absent-by-default APP_CONFIG_CACHE path so tests ignore bootstrap/cache/config.php. Non-SQLite tests additionally require HOA_ALLOW_DESTRUCTIVE_TEST_DATABASE=true and a database name containing test or tests. These guards prevent test migrations from reaching local UAT or production-like databases.

## Local setup

```powershell
& "F:\Xampp 8\php\php.exe" "C:\ProgramData\ComposerSetup\bin\composer.phar" install
npm.cmd install
Copy-Item .env.example .env
& "F:\Xampp 8\php\php.exe" artisan key:generate
& "F:\Xampp 8\php\php.exe" artisan migrate --seed
& "F:\Xampp 8\php\php.exe" artisan storage:link
npm.cmd run build
.\serve.bat
```

Open:

- Public site: `http://localhost/`
- Homeowner panel: `http://127.0.0.1:8000/homeowner`
- Legacy resident portal: `http://127.0.0.1:8000/portal/login`
- Admin panel: `http://localhost/admin`
- Staff panel: `http://localhost/staff`

## Seeded development accounts

| Role | Email | Password |
| --- | --- | --- |
| HOA Admin | `admin@southville.test` | `Southville2026!` |
| HOA Staff | `staff@southville.test` | `Southville2026!` |
| Homeowner | `resident@southville.test` | `Southville2026!` |

These credentials are development fixtures. Delete or rotate them before deployment.

For an approved non-production UAT run, seed the additional second-Staff, second-Homeowner, and pending-Homeowner identities with `php artisan db:seed --class="Database\Seeders\UatSeeder"`. The command is idempotent and refuses to run in production; account details and cleanup requirements are in the [UAT execution record](docs/UAT_EXECUTION_RECORD.md).

## Quality checks

```powershell
& "F:\Xampp 8\php\php.exe" "C:\ProgramData\ComposerSetup\bin\composer.phar" qa
npm.cmd run build
npm.cmd run test:browser
```

## Production configuration

Set `APP_ENV=production`, `APP_DEBUG=false`, `APP_TIMEZONE=Asia/Manila`, a production `APP_KEY`, MySQL credentials, `SESSION_SECURE_COOKIE=true`, and real SMTP credentials. Run migrations with `--force`, build assets, cache configuration/routes/views, and supervise `php artisan queue:work`. Point the web root to `public/`, not the repository root.

Resident documents, generated reports, certificates, and payment proofs are stored on the private local disk and served through authorization checks. Announcement banners use the public disk and require `artisan storage:link`. For multi-server deployment, move storage to S3-compatible object storage and use temporary URLs for private objects. Place the application behind HTTPS and an edge/WAF service for volumetric traffic protection.

See [IMPLEMENTATION_PLAN.md](IMPLEMENTATION_PLAN.md) for module status and next milestones.

Operational instructions are in [Deployment](docs/DEPLOYMENT.md), [Operations and recovery](docs/OPERATIONS.md), and [User guide](docs/USER_GUIDE.md).
The release-candidate survey and scoring rules are in the [ISO/IEC 25010 evaluation and UAT instrument](docs/ISO_25010_EVALUATION.md). Use the separate [UAT execution record](docs/UAT_EXECUTION_RECORD.md) to witness role journeys, attach evidence, track defects, and authorize release.
