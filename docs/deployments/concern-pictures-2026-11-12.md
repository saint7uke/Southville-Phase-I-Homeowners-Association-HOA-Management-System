# Private concern pictures — 12 November 2026

## Change

JPEG/PNG attachments render inside the homeowner concern-details modal. Existing downloads remain, and PDF/Word attachments stay download-only. The new `case-attachments.preview` GET route reuses attachment ownership authorization and private storage. It rejects missing/archived parents, non-images, mismatched MIME types and files above the existing 5 MB upload ceiling. Actual JPEG/PNG image headers are checked before streaming; responses use inline disposition, private/no-store caching and nosniff.

The scoped image layout preserves proportions, constrains pictures to the modal width/60vh, and retains native modal scrolling. Alpine handles loading, failure and cached-image completion. English/Filipino alternative text and status guidance are translated without altering filenames or resident prose. Failed images leave downloads and dismissal usable.

## Local verification

- `php artisan test --compact --filter="CaseAttachmentTest|InterfaceRevisionTest"`: 17 passed, 156 assertions. Includes actual JPEG/PNG bytes, response headers, ownership/guest/archived/missing access, false MIME types, existing downloads and concern modal regressions.
- `npm.cmd run test:tooling`: 21 passed.
- `npm.cmd run build`: passed; existing runtime hero-image URL warnings remain.
- Targeted Pint checks passed for controller, routes and affected PHP tests/fixtures/router.
- `node scripts/run-interface-browser-checks.mjs --concern-pictures`: 2 passed (1280px/390px Chromium). Actual JPEG/PNG responses, dimensions, three download actions including PDF, light/dark layout, focus return, translated alternative text/error guidance, simulated load failure and Close dismissal passed. Screenshots visually inspected; evidence in `C:/Users/bargo/AppData/Local/Temp/hoa-alert-browser-interface-N3UK3S/`. Uses temporary SQLite/private storage, synthetic accounts, array mail and disabled SMS.
- Initial broad browser checks could not load fixture pictures and the desktop case exhausted its overall time budget. Diagnosis showed Windows CLI-server initialized its storage path before environment loading, unlike fixture seeding. A guarded test-only router now initializes the isolated storage/view paths before application construction. It is not deployed or registered as a production route. Browser results are stored in each isolated runtime to avoid concurrent-run collisions. The focused failure simulation forces a new image request rather than relying on re-opening an already decoded image; each case restores English because locale preferences intentionally persist between logins.

## Release and deployment boundary

No migration, notification send, default queue processing, consent change or record rewrite is required. User authorized SSH rollout after verification. The explicit `scripts/package-concern-pictures.mjs` allowlist includes controller, routes, detail template, scoped styles/locale hook, translations and complete matching build assets. Tests, credentials, proprietary fonts and records are excluded.

Prepared archive: `C:/Users/bargo/AppData/Local/Temp/hoa-concern-pictures-release-sn7UgF/release.tar.gz` (33 files), SHA-256 `c60fb1ae7904b241527676c63498a01fd9a0f87a59b2e2ecb720647392f2d133`.

Deploy only after browser verification. Compare production source, back up affected files/build and database privately, install the reviewed manifest last, refresh the existing route cache only if enabled, and refresh compiled views. Verify checksums, health, route registration and protected-state snapshots without reading resident attachments. Preserve `.env`, `.htaccess`, SMS configuration, cron, queued/failed jobs and business records.

## Authorized SSH deployment completed

Deployed 12 November 2026, approximately 16:48 Manila / 08:48 UTC. Private directory: `/home/u130796816/.hoa-deploy/concern-pictures-20261112-v1`.

- Reviewed all seven production source/template/translation differences; only planned image-preview changes were present. Uploaded archive checksum matched the local manifest.
- Private source/assets backup `before-clean.tar.gz`: 3,179,800 bytes. Database backup `database.sql`: 1,138,868 bytes. Both are outside the web root with private permissions.
- Installed 33 allowlisted files, build manifest last. Controller/routes PHP syntax checks passed. Existing route caching was enabled, so `php artisan route:cache` and `php artisan view:cache` were refreshed successfully. Both attachment download and preview routes are registered.
- Verification: 33 matching file hashes, zero tracked table changes, protected `.env`/`.htaccess`/cron fingerprints unchanged. Snapshot includes complaints, attachments and inquiry activities as well as household, certificate, payment, notification and settings tables. Queued/failed counts remained 73/six. SMS delivery `updated_at` alone was excluded from fingerprints to tolerate existing housekeeping; its other fields were checked.
- Read-only public checks: health 200; concerns and real-ID preview guest requests 302 to authentication; new CSS and locale JS 200. No resident picture was opened in production. Authenticated desktop/mobile rendering was verified only against isolated synthetic fixtures.
- No migration, production concern submission, notification send, queue processing or configuration change.

Rollback: restore the affected source and `public/build` from the private source backup, then refresh route and view caches. The previous controller/routes remove the preview endpoint while preserving existing downloads. No database restoration is needed for this release; keep the database backup private.
