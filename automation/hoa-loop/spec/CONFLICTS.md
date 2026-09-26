# Mandatory Reconciliation Decisions

The research-only first cycle must verify and expand this list, recommend a path, and then stop for human approval.

## Dependency generations

- Brief requests Filament 3, React 18, Vite 5, and Tailwind 3.
- Repository currently declares Filament 4, React 19, Vite 7, and Tailwind 4.
- Default recommendation: preserve the newer installed majors and translate requirements to their APIs. Do not downgrade without explicit approval.

## Homeowner surface

- Brief requires a third Filament panel at `/homeowner` with its own guard and resources.
- Repository currently has a custom Blade/controller portal at `/portal` and only Admin/Staff Filament providers.
- Default recommendation: implement `/homeowner` as the target panel, keep temporary compatibility redirects or a staged migration for `/portal`, and remove the old surface only after regression/UAT approval.

## Authentication and Sanctum

- Brief says Sanctum is removed and all panels use session authentication.
- The repository initially required `laravel/sanctum`.
- Resolution: the dedicated dependency audit found no runtime use; Sanctum was removed and the complete protected regression suite remained green.

## Database

- Brief targets MySQL 8; local development currently uses SQLite.
- Keep SQLite for fast automated tests unless MySQL-specific behavior requires a dedicated CI lane. Production schema must also be verified against MySQL before release.

## Existing schema naming

- Brief names `dues_schedules`, `payment_records`, `requests`, and Spatie `activity_logs`.
- Repository currently contains `dues_settings`, `payments`, `service_requests`, and a custom audit model/table.
- Prefer compatibility and incremental migrations over renaming working tables. Any rename requires a data migration and rollback plan approved by a human.

## Landing page

- The current landing page already uses React, GSAP, responsive components, and a real HOA hero image.
- Brief requests different copy, navigation, typography, a contact API, and `/homeowner/login` CTAs.
- Preserve completed visual work; implement missing functional requirements and route migration after the architecture decision.
