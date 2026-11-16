# Private project saves — local verification and deployment preparation

## Behavior

Planned and In Progress projects save privately. The reactive status selector clears and disables Publish for unfinished statuses. Returning to Completed enables Publish without selecting it. Both dialogs use **Save Project** and explain the completed-only publication rule in English/Filipino.

The table distinguishes completion status from publication, retaining the publication timestamp. Changing a project with a publication timestamp to an unfinished status requires a visible confirmation checkbox. The backend rechecks confirmation against the locked record before changing it; cancellation and failed validation make no changes. Confirmation is transient form input, not a database column.

Backend publication validation, today-or-earlier dates, administrator permissions, private image storage, audit logging, upload rollback and archive/restore behavior remain intact. No migration, new notification or automatic publication is introduced.

## Reviewed runtime files

- `app/Filament/Pages/Projects.php`
- `app/Actions/Projects/ManageProject.php`
- `resources/views/filament/pages/projects.blade.php`
- `lang/en.json`
- `lang/fil.json`
- Complete matching `public/build` output, including its manifest and bundled translation dictionaries.

Test/support additions: `tests/Feature/ProjectPublicationTest.php`, `tests/Browser/projects.spec.js` and `scripts/run-project-browser-checks.mjs`. Do not deploy the test runner or synthetic fixtures as application code.

## Local verification

PHP regressions were run using process-only overrides: `APP_ENV=testing`, an unused `APP_CONFIG_CACHE` path, `DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:`, empty `DB_URL`, `MAIL_MAILER=array`, `QUEUE_CONNECTION=sync`, `CACHE_STORE=array`, `SESSION_DRIVER=array` and `TEXTBEE_ENABLED=false`. No `.env` was edited.

```text
php vendor/phpunit/phpunit/phpunit --filter 'ProjectPublicationTest|PhaseThreeTest' --testdox
node scripts/run-project-browser-checks.mjs
npm.cmd run test:tooling
npm.cmd run build
php vendor/bin/pint --test app/Filament/Pages/Projects.php app/Actions/Projects/ManageProject.php tests/Feature/ProjectPublicationTest.php
```

- PHP: **21 tests / 170 assertions passed**. Includes private create/edit with images, status reactivity, confirmation/cancellation, no success on missing confirmation, Completed publication, restored privacy, future dates, malformed publication, invalid uploads, storage rollback, public image protection, localization and authorization.
- Tooling: **16 tests passed**; JavaScript syntax and diff whitespace checks passed.
- Production asset build: **130 modules passed**. Existing runtime hero-image URL resolution warnings remain unchanged.
- Browser runner uses a fresh temporary SQLite database, synthetic accounts, loopback server, ephemeral application key, isolated storage and disabled SMS/array mail. Fixtures seed under `testing`; the HTTP server uses `local` to exercise genuine multipart uploads rather than Livewire's request-local unit-test fake disk. No application runtime workaround was introduced.
- Final Chromium browser run: **two scenarios passed**, desktop at 1280px and mobile at 390px. Coverage includes a genuine desktop image upload, reactive fields, private save, explicit publication, cancellation and confirmed unpublishing, with one action-specific success modal per outcome. Both confirmation screenshots were visually inspected: labels, helper text, confirmation and action buttons wrap correctly without clipping. Artifacts are retained privately at `C:/Users/bargo/AppData/Local/Temp/hoa-alert-browser-projects-dDUCCK`.
- The first desktop attempt timed out opening the dialog while Livewire was still loading. Tests now explicitly await the dialog. A later upload attempt exposed the runner's `testing` fake-disk configuration, corrected by the isolated real-HTTP setup above. Neither failure was bypassed by removing assertions.
- Initial PHP test failures were test setup errors (protected schema accessor, unavailable `tomorrow()` helper and comparing a newly inserted model with a hydrated row). They were corrected without changing application behavior.

## Separate SSH rollout checklist

1. Compare the five reviewed runtime files and complete asset manifest with production, preserving unrelated changes. Back up affected files privately and record checksums before replacement.
2. Deploy only reviewed runtime files and the complete matching build. Refresh compiled Blade views. No migration, environment change or worker restart is needed.
3. Verify deployed checksums, public homepage response, and compiled asset availability using read-only checks. Do not create or modify production projects for smoke testing without separately designated test records.
4. If validation fails, restore the matching source/assets backup and refresh compiled views.

Production projects, `.env`, `.htaccess`, records, SMS settings, cron jobs and queued/failed jobs remain untouched during this implementation. No SSH connection, live email or SMS was used.

## Completed SSH rollout — 2026-11-12 (Asia/Manila)

- Private release/rollback directory: `/home/u130796816/.hoa-deploy/projects-private-save-20261112-v1`. The private `before.tar.gz` backup contains the affected production source and complete previous build, with `before-state.json` storing only protected-file and table fingerprints/counts.
- Production comparison confirmed the five runtime-file differences were the reviewed project-save changes. The English/Filipino dictionaries differed only by the five new project messages. No unrelated production source changes were overwritten.
- Uploaded package SHA-256: `a46f942213abed21dca178f540ec84c07f0efcfc6cc4307852dff942253d89ee`; the remote checksum matched. Staged PHP/Blade lint passed.
- Installed **five reviewed runtime files and 27 matching build files**. Individual files were replaced atomically; the asset manifest was installed last. Old hashed assets were retained for already-open browser tabs, not deleted.
- `php artisan view:clear` and `php artisan view:cache` completed. No migration, `.env` change, configuration-cache rewrite, worker restart or queue processing was performed.
- Post-installation verification: **32 files matched, zero mismatches**; `.env` and `.htaccess` fingerprints remained unchanged. All **14 tracked tables** retained their row counts and content fingerprints, including six projects, seven project images, 73 queued jobs and six failed jobs. Household/service-request, financial, email/SMS tracking and system-setting records were unchanged.
- Read-only HTTP checks returned **200** for `/up`, `/` and `/build/assets/translations-DR0lOIpw.js`. The deployed dictionary contains the new Save Project text.
- No production project was created, edited, published or archived for testing; no notification was sent. Production interactive save testing remains deliberately unperformed. Functional save/publication/confirmation checks were completed locally against synthetic data as recorded above.
- The private server-side `verification.json` records the checksum and unchanged-state results. Cron jobs and existing SMS configuration were not modified.
