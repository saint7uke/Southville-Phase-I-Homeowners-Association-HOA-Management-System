# Household head consistency — SSH deployment

Deployed to `https://southvillehoams.com` on 9 November 2026 through authenticated, host-key-verified SSH. The application is live after a brief maintenance window.

## Installed and verified

- Installed the 18 application/view/translation paths in `household-head-source-manifest.txt` and 25 complete matching Vite build files: **43 checksums matched** after installation. All 13 non-view PHP files passed server-side syntax checks before installation.
- Compared existing files against the preceding deployed release or the repository baseline. The existing HouseholdMembers page had earlier feedback changes; its production/local diff was explicitly reviewed before replacement. Shared prerequisites matched. Laravel 12.69.2, Filament Tables 4.13.4 and Livewire 3.8.8 matched locally; no dependency installation or upgrade was performed.
- Rebuilt optimized autoloading without Composer scripts, cleared Laravel caches, and rebuilt configuration and Blade caches. Both new assignment/review classes are available and the read-only audit command runs successfully.
- **No migration, head assignment, reconciliation, backfill or test-record mutation was performed.**
- Compared exact row counts and SHA-256 row-data fingerprints before/after installation for users, homeowners, households, household memberships, dues, payments, complaints, service requests, certificates, audit logs, workflow/announcement mail tracking, contact messages/activities, administrator preferences, migrations, queued jobs and failed jobs. Every comparison matched.
- Production `.env` and root `.htaccess` remained byte-for-byte unchanged. Private documents/fonts were not transferred. Certificates and request records were unchanged.

## Read-only inconsistency report

The pre-install report and the installed `php artisan hoa:audit-household-heads --json` command agree:

| Household | Selected member ID | Current Head-label IDs | Finding |
| --- | --- | --- | --- |
| 2 | 1 | 7 | Conflicting assignment; HOA must confirm the actual head |
| 3 | None | None | Head unassigned |
| 4 | None | None | Head unassigned |
| 5 | None | None | Head unassigned |

Household 2 and all membership rows remain unchanged, including historical roles. Its stored `needs_review` value was not rewritten; the new interface derives and displays the conflict warning without changing records.

## Private backups

Server directory outside the document root:

`/home/u130796816/.hoa-deploy/household-head-20261109-v1`

Contains `source-backup.tar.gz`, `database.sql`, a fresh maintenance-window `database-at-deployment.sql`, file/row fingerprints, and before/after read-only head reports. The directory is mode 0700; source/database archives and metadata are mode 0600. Do not publish or attach these archives. No rollback was required.

## Production checks and limitations

- `/up`, `/login`, and public registration returned HTTP 200. Registration still omits Street and retains blank/A/B/EXPANSION options.
- Admin/Staff Households and Household Members routes, plus Homeowner My Household, returned HTTP 302 when unauthenticated. Existing protected Users, Announcements, Service Requests and Staff certificate-create routes also redirected to login.
- Both current app and panel JavaScript assets returned HTTP 200.
- No authenticated production assignment, clearing, export or restoration was attempted without designated synthetic test records. Actual multi-process MariaDB contention remains unverified; local stale-state tests are deterministic and SQLite-based.
- Prior local evidence: 239 PHP regressions / 1,658 assertions; final focused 38 tests / 152 assertions; three actual Chromium assignment/review/clear flows; ten JavaScript tooling tests; production build and scoped formatting passed. See `../audits/household-head-consistency.md`.

## Queue boundary

The **64 queued jobs and six failed jobs retained their exact contents**. No worker, scheduler, cron change, queue restart/drain, failed-job retry, email, certificate issuance or real-homeowner test was initiated. Existing backlog processing remains a separate decision requiring review and explicit authorization.
