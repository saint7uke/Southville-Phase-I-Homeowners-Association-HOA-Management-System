# Southville Phase I HOA Information System

A Laravel 12 information system for Southville Phase I, Brgy. Inocencio, Trece Martires City, Cavite. It provides separate Filament panels for HOA administrators and staff, a resident portal, a React public landing page, role-based access, queued notifications, private attachments, audit history, and payment exports.

## Stack

- Laravel 12.64 and PHP 8.2+
- Filament 4 admin and staff panels
- Spatie Laravel Permission
- React 19, Vite 7, Tailwind CSS 4
- Laravel Excel
- SQLite for local development and tests; MySQL 8 recommended for production

The original brief named Filament 3 and Tailwind 3. This implementation uses their current Laravel 12-compatible major versions while preserving the specified workflows.

## Local setup

```powershell
& "F:\Xampp 8\php\php.exe" "C:\ProgramData\ComposerSetup\bin\composer.phar" install
npm.cmd install
Copy-Item .env.example .env
& "F:\Xampp 8\php\php.exe" artisan key:generate
New-Item -ItemType File database\database.sqlite -Force
& "F:\Xampp 8\php\php.exe" artisan migrate:fresh --seed
npm.cmd run build
& "F:\Xampp 8\php\php.exe" artisan serve
```

Open:

- Public site: `http://localhost/`
- Resident portal: `http://localhost/portal/login`
- Admin panel: `http://localhost/admin`
- Staff panel: `http://localhost/staff`

## Seeded development accounts

| Role | Email | Password |
| --- | --- | --- |
| HOA Admin | `admin@southville.test` | `Southville2026!` |
| HOA Staff | `staff@southville.test` | `Southville2026!` |
| Homeowner | `resident@southville.test` | `Southville2026!` |

These credentials are development fixtures. Delete or rotate them before deployment.

## Quality checks

```powershell
& "F:\Xampp 8\php\php.exe" "C:\ProgramData\ComposerSetup\bin\composer.phar" qa
npm.cmd run build
```

## Production configuration

Set `APP_ENV=production`, `APP_DEBUG=false`, a production `APP_KEY`, MySQL credentials, `SESSION_SECURE_COOKIE=true`, and real SMTP credentials. Run migrations with `--force`, build assets, cache configuration/routes/views, and supervise `php artisan queue:work`. Point the web root to `public/`, not the repository root.

Uploads are stored on the private local disk and served through authorization checks. For multi-server deployment, move private storage to S3-compatible object storage and use temporary URLs. Place the application behind HTTPS and an edge/WAF service for volumetric traffic protection.

See [IMPLEMENTATION_PLAN.md](IMPLEMENTATION_PLAN.md) for module status and next milestones.
