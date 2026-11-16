# My Concerns detail modal — 12 November 2026

## Scope

Implemented locally and deployed over SSH on 12 November 2026 after separate user authorization. The homeowner eye action now mounts a native Filament read-only action modal. The former inline detail section and selected-record public property were removed. Both mounting and content rendering resolve records through `ResidentConcerns::detail`, retaining ownership, eligibility, soft-delete and sent-reply restrictions. Array identifiers are rejected before resolution.

The modal includes subject, type, reference, status, submitted date, body, complaint attachment links/resolution notes or sent inquiry replies. Blade escapes all resident content. Filament supplies focus trapping, Escape/close controls, viewport sizing, internal scrolling and sticky header/footer. Scoped detail CSS wraps long content. Loading disables row triggers. Submission continues refreshing the list with its existing action-specific notification and does not select a detail record. Explicit translation markers and a concern-modal-only close-control translation hook handle live English/Filipino switching without translating user prose.

No migration, legacy-portal change, workflow mutation, notification send, configuration edit or queue processing is included.

## Verification

- `php artisan test --compact --filter="InterfaceRevisionTest|CaseAttachmentTest"`: 14 passed, 120 assertions. Includes both detail types, sent-reply filtering, escaping, attachment destination, read-only behavior, filter/pagination preservation and malformed/missing/archived/other-owner rejection.
- `php vendor/bin/pint app/Filament/Homeowner/Pages/MyConcerns.php tests/Feature/InterfaceRevisionTest.php`: passed.
- `npm.cmd run test:tooling`: 21 passed.
- `npm.cmd run build`: passed. Existing hero-image runtime URL warnings remain; no new asset dependency introduced.
- `node scripts/run-interface-browser-checks.mjs --grep "resident concerns"`: 2 passed (1280px and 390px, Chromium). Both Inquiry and Complaint modals, no inline/automatic detail panel, Escape/footer/icon dismissal, trigger focus return, translated headings, long text and viewport bounds passed. Synthetic submission success feedback remains unchanged. Dark-mode long-content screenshots visually inspected at both widths. Uses disposable SQLite/storage, synthetic accounts, array mail and disabled SMS. Livewire action partials are verified through mounted-action content in PHP and actual dialogs in browser checks.
- Browser evidence: `C:/Users/bargo/AppData/Local/Temp/hoa-alert-browser-interface-pcq1EW/` (`concern-modal-*` and `complaint-modal-dark-*`). Initial checks exposed an ambiguous test selector for two Close controls and missing live translations on the new modal; both were corrected before the successful rerun.
- Release manifest rechecked against workspace: zero checksum mismatches. Actual email/SMS delivery was not exercised.

## Separate release preparation

`node scripts/package-concern-modal.mjs` creates an explicit allowlisted archive containing the page class, list/detail templates, scoped CSS and complete matching `public/build` tree, with a SHA-256 manifest outside the repository. It excludes credentials, migrations, records and proprietary font sources. This is preparation, not deployment.

Prepared release: `C:/Users/bargo/AppData/Local/Temp/hoa-concern-modal-release-w2JxrE/release.tar.gz` (31 files); SHA-256 `d0379fa35086cf47bd4ca1aa9dc8c8f8fdffb527021a8cb39bde661676083614`. Includes the narrowly scoped locale hook source and matching compiled assets.

For a separately authorized SSH release: compare production files first, make private backups, deploy only reviewed files and matching assets, refresh compiled views/caches, verify checksums and perform read-only checks. Leave `.env`, `.htaccess`, SMS settings, cron, jobs and production records unchanged. Rollback restores the private source/assets backup and refreshes caches; no database rollback is required.

## Authorized SSH deployment

Completed at approximately 08:14 UTC / 16:14 Manila, 12 November 2026.

- Private staging/backup directory: `/home/u130796816/.hoa-deploy/concern-modal-20261112-v1` (outside the web root). Reviewed source differences matched the modal-only change and scoped styling/translation additions.
- Archive SHA-256 matched before extraction. Private source/assets backup: `before-clean.tar.gz` (2,906,788 bytes, mode 600); full database backup: `database.sql` (1,137,672 bytes, mode 600).
- Installed 31 allowlisted source/asset files, manifest last. PHP syntax check passed; `php artisan view:cache` succeeded.
- Post-install verification: all 31 checksums matched, no tracked table changes, `.env`, `.htaccess` and cron fingerprints unchanged. Queue counts remained 73 queued and six failed. SMS/settings, households, certificates, requests, payments, email tracking and audit rows in the tracked snapshot were unchanged; only SMS delivery `updated_at` was excluded from the comparison to tolerate existing cron housekeeping.
- Read-only HTTP checks: `/up` 200; anonymous `/homeowner/my-concerns` 302; deployed app CSS and locale JS 200.
- No migration, worker/scheduler invocation, message send, resident enrollment or production concern submission. Authenticated production modal interaction was not exercised; desktop/mobile modal behavior was verified locally with synthetic fixtures.

Rollback: restore affected source files and `public/build` from `before-clean.tar.gz`, remove the newly introduced `resources/views/filament/homeowner/concern-details.blade.php` if rolling back entirely, then run `php artisan view:cache`. Keep the database backup private; this presentation-only release requires no database restoration.
