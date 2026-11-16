# Registration independent scrolling — 12 November 2026

## Change

Registration-only classes and stylesheet constrain the desktop card to the viewport with 1rem outer padding. Above 720px width and 640px height, branding stays vertically centered outside the scrollable right panel. The right panel is top-aligned with `min-height: 0`, independent vertical overflow and stable scrollbar space. Narrow or short viewports stack panels with normal document scrolling and visible branding description. Ownership-step changes reset the active scroll position without clearing field values. No field, validation, upload, notification or submission policy changed.

## Local verification

- `npm.cmd run build`: passed, 130 modules. Existing hero-image runtime URL warnings unchanged.
- `node scripts/run-interface-browser-checks.mjs --registration-scroll`: **7 passed** on isolated SQLite/storage, array mail, disabled SMS and blocked external requests. Six sizes: 1440×900, 1024×768, 721×641, 390×844, 1280×600 and 683×384; English and Filipino. The last size exercises the compact CSS viewport equivalent of a typical 1366×768 display at 200% browser zoom, not a claim of manually testing native browser zoom.
- Checks establish stationary branding, document/independent scroll boundaries, branding containment, bottom privacy/submit accessibility, focus-induced field scrolling, ownership-step reset, preserved values and no horizontal overflow. Login/forgot-password were checked for absence of registration scoping. Desktop screenshot visually inspected. Artifacts: `C:/Users/bargo/AppData/Local/Temp/hoa-alert-browser-interface-2Jz9n8`.
- Initial browser failures were addressed: compact resets now reveal the active step, not the preceding heading; the focus test explicitly blurs before refocusing an already-focused input. Final rerun passed all cases.
- `php artisan test --compact --filter='PortalWorkflowTest|InterfaceRevisionTest|RegistrationEmail|RegistrationPhone'`: **27 passed, 157 assertions**.
- Broader `PortalWorkflowTest|Registration|InterfaceRevisionTest`: **53 passed / 1 failed**. Outstanding, unrelated stale assertion: `RegistrationAddressAvailabilityTest::test_endpoint_validates_input_and_is_throttled` still expects removed `street` validation; actual visible-field errors are Phase/Block/Lot/ownership. This layout task does not change that test or backend validation.
- `node --test tests/Tooling/*.test.mjs`: **21 passed**, including two new scoping/reset checks. JavaScript syntax and targeted `git diff --check` passed.
- A full valid application submission/confirmation with uploads was not performed in this layout-specific browser suite. Confirmation implementation and styling were not changed. No production submissions or messages were sent.

## Authorized SSH deployment

Completed at approximately **15:28 Asia/Manila** following the user's explicit SSH instruction.

- Archive: `C:/Users/bargo/AppData/Local/Temp/hoa-registration-scroll-release-tCkiDf/release.tar.gz`; SHA-256 `5a52d22766b42544cc574f63b675e59a8236639a7899a782ae08cd1c4118ad4f`.
- **29 files**: three reviewed source files and the complete matching build. Packaging script: `scripts/package-registration-scroll.mjs`. No credentials, fonts, customer records or migrations included.
- Server private release: `/home/u130796816/.hoa-deploy/registration-scroll-20261112-v1`.
- Production diff confirmed only the registration-specific changes. Private source/build and database backups created before installation. Clean source archive `before-clean.tar.gz`: 2,794,276 bytes, SHA-256 `9c7f0c295900e6888b7fd6e2bebd082cdcb51fdc11e70961f4c1dcc6f446c017d`; extracted backup matched pre-deployment file hashes. A private local source copy was downloaded.
- Single-transaction database backup `database.sql`: 1,142,006 bytes, SHA-256 `9daa2756e36d0388bb7bf20b4b4570b894fe10ecad37166e3d5c5434616a7124`. Kept privately outside webroot; not downloaded or restored.
- Brief maintenance window, manifest installed last with old hashed assets retained, Laravel config/route/view caches rebuilt, application returned live. **29 verified file hashes, zero mismatches**.
- Read-only HTTP checks: registration, new landing stylesheet, health, login, forgot-password and homepage all 200. Deployed registration contains its scoped classes/reset helper and the stylesheet contains scoped rules.
- Protected `.env`, root `.htaccess` and readable user-crontab hashes unchanged. Checked business-table fingerprints and migration rows unchanged. Jobs remain **73 queued / six failed** with unchanged full row fingerprints. SMS substantive-column comparison excludes only background-maintained `updated_at`; no substantive SMS delivery change detected.
- No migration, worker/scheduler operation, notification send, enrollment or business-record mutation initiated. Existing household/request/certificate/payment records preserved.

## Rollback

Restore affected source and matching build from the private `before-clean.tar.gz`, then rebuild Laravel caches and return live. No database rollback is needed; do not restore the database automatically. Keep `.env`, `.htaccess`, SMS configuration, cron and existing jobs untouched.
