# Exact Calibri installation — 2026-11-12

## Scope and authorization

Installed the user-selected Windows Calibri with confirmed server-use and PDF-embedding rights. No application source, asset, migration, source approval, financial record or issued document was deployed or changed. No production receipt was generated, including payment 2.

## Local verification

- Selected `C:/Windows/Fonts/calibri.ttf`, 1,650,632 bytes.
- SHA-256: `57e93ff143572bcc092c1f56202d43ddc07bb119aa104c6d7af163470fb551f6`, matching the existing pinned receipt layout. The downloaded font was not used; original font files were unchanged.
- Process-only `CERTIFICATE_BUTAW_FONT` and `LARAVEL_STORAGE_PATH` overrides; no local `.env` edit. Synthetic storage and generated TCPDF cache isolated under `C:/Users/bargo/AppData/Local/Temp/hoa-butaw-calibri-20261112`.
- File-only helper `local-check.php`: cold and warm registration returned `calibri` and the pinned checksum; produced blank, filled and draft synthetic PDFs without database access or source approval.
- Existing `scripts/check-butaw-layout.py`, with its review directory redirected in memory to isolated storage: passed. Letter page; one original slip; source-to-blank raster delta maximum 1/255; 2,190 changed color channels confined to mapped blanks; exact Calibri 11.04 pt; Unicode; draft watermark; no form fields. Rendered slip visually inspected: readable text, aligned blanks, no overlap or clipping. The existing source/background and mapping were not edited.
- `php vendor/phpunit/phpunit/phpunit tests/Feature/ButawReceiptTest.php --display-skipped`: **29 passed, 198 assertions, no skips** using isolated SQLite/storage and the selected font.

## Production SSH installation

Private release/backup directory: `/home/u130796816/.hoa-deploy/butaw-calibri-20261112-v1`.

- Before installation the effective cached `certificates.butaw_font` was `null`; there was no previous effective font file to back up.
- Backups: `backup.env`, `backup-config.php`, `before-config.serialized` and `rollback.json`, all private. Original `.htaccess` hash and cron contents retained for comparison.
- Installed font: `/home/u130796816/.hoa-private/fonts/calibri.ttf`; directories 0700, font 0600, owned by application account `u130796816`. Font remains outside the public web root, Git and application release assets.
- Updated only `CERTIFICATE_BUTAW_FONT` in production `.env`, then ran Laravel `config:cache`.
- Verified uploaded/installed checksum, PHP readability, effective cached path and two TCPDF registrations. Registration does not render or issue a production receipt.
- Compared old/new cached configuration recursively and verified the font setting was the only difference; compared environment bytes excluding that single setting. `.htaccess` and cron comparisons passed.
- The first installation helper's post-cache comparison stopped on PHP's non-comparable `DateInterval` objects. Installation/cache rebuild had succeeded. A separate read-only verification helper compared serialized leaf values, confirmed no unexpected changes and completed registration checks. No business operation was involved.
- No workers/scheduler were started; queued/failed jobs, SMS settings and business records were not processed or edited. No production resident document was opened.

## Rollback

Before rollback, compare the current environment/cache with this release to avoid overwriting later changes. Restore only the prior font setting (absent/null in the backup) and rebuild configuration using `/usr/bin/php artisan config:cache` from the application root. The private original `.env` and cached configuration are retained for recovery; restore whole files only if no intervening configuration changes exist. The font may remain private and unused. Do not alter receipts, approvals or financial records.

## Evidence limits

Local synthetic rendering and receipt regression tests passed. Production checks verified font configuration, integrity, readability and TCPDF registration only. They deliberately do not claim that a production receipt was generated, issued or visually inspected.
