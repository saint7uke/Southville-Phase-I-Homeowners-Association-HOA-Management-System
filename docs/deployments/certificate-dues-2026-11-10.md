# Certificate dues restriction — 2026-11-10

## Reviewed package

Runtime file list: `certificate-dues-2026-11-10-manifest.txt`. Nineteen PHP/Blade/translation files, including three new files, plus the complete matching asset build. No migration or production environment change.

Behavior and test details: `docs/audits/certificate-dues-eligibility.md`.

## Safety and preparation

Read-only source comparison recognized prior reviewed releases and manually reviewed the three remaining form differences. Production preflight: 67 queued jobs, six failed jobs, request 5 Completed, household 2 head member 1. No assignments, requests, obligations or payments were changed.

Private source/database backups and installed-file checksums are required before declaring deployment complete. SMS worker/maintenance locks protect the brief installation window; no worker or scheduler is started, and no jobs are processed. Preserve `.env`, `.htaccess`, SMS configuration, existing cron jobs, fonts and issued documents.

## Results

- Broad regression run: 294 tests / 1,865 assertions, with 289 passing and five prerequisite-dependent skips. Final targeted certificate/eligibility run: **42 tests / 128 assertions passed**, including valid-preview-then-new-debt standalone issuance denial. Runs overlap and are not summed.
- Disposable MariaDB: **three concurrency cases / 18 assertions passed**. The temporary local instance was shut down afterward; its isolated test database is retained privately for inspection. No production database credentials were used for it.
- Browser: both Admin and Staff approval denial passed, followed by a passing homeowner mobile test covering cancellation, one error modal, no request on failure, full cash settlement and successful subsequent submission. Three scenarios passed across the final focused reruns. Initial failures were fixture selectors for required-label asterisks, the existing SweetAlert confirmation (rather than a native Filament modal), and insufficient local-server wait time; no application behavior was weakened to pass them.
- Pint and diff whitespace checks passed; 14 tooling tests passed; Vite production build succeeded with existing hero-image URL warnings.
- The separately reproduced pre-existing Inactive-account email assertion still fails; the notification eligibility policy is intentionally unchanged. Source/font-dependent certificate cases remain explicitly skipped where unavailable.

## Completed SSH rollout

- Private release directory: `/home/u130796816/.hoa-deploy/certificate-dues-20261110-v1`.
- Private source backup: **1,825,228 bytes**; private database backup: **1,005,203 bytes**.
- Installed **19 reviewed runtime files and 27 matching build files**. Staging linted 15 PHP files; installed source/build checksums matched.
- Laravel configuration cache and compiled Blade views rebuilt. The application returned live; `/up` returned **HTTP 200**.
- All **30 tracked tables** retained their pre-installation row counts and content fingerprints. Protected `.env`, `.htaccess` and the SMS-worker wrapper remained unchanged. No migration, queue processing or notification send occurred.
- Post-deployment read-only eligibility classification: **three outstanding, one eligible** across current resident accounts. All 19 runtime files matched local reviewed source.
- Request 5 remains Completed; household 2 head remains member 1. The 67 queued and six failed jobs were unchanged. No production request, approval, payment, preview artifact or issued certificate was created for smoke testing.

## Subsequent local-only verification follow-up

The earlier skipped/failing results remain above as historical rollout evidence. A separate test/runner/documentation follow-up corrects the Inactive-account email expectation while preserving the existing Active-and-verified workflow policy. New tests explicitly execute the delivery job with fakes and cover committed intent, rollback, recipient suppression and duplicate prevention.

`node scripts/run-readiness-gap-checks.mjs --sqlite-only` passed **187 tests / 1,501 assertions / zero skips**, including both Cohabitation cases using genuine Windows Arial and synthetic source fixtures. `npm.cmd run test:tooling` passed **16 tests**; changed PHP formatting checks passed. The runner uses process-only configuration and private temporary test artifacts, not production credentials or configuration.

The MariaDB runner creates its own loopback-only disposable instance and runs the three tenant cases plus three certificate-dues races. A Windows readiness-marker race in the tenant test harness was identified and corrected without changing application locks or weakening outcome assertions. The final rerun passed **six tests / 38 assertions / zero skips** and shut down its temporary server normally. Across the two non-overlapping final runner groups, **193 tests / 1,539 assertions passed**. All five original skipped cases executed successfully in their intended environments. Exact names and retained local evidence are in the linked verification guide.

This follow-up makes **no SSH connection or production deployment**. No production `.env`, permissions, sources, fonts, records, queues or notification eligibility are changed, and no test messages are sent. There is no application runtime package to deploy for these verification changes. See `docs/testing/readiness-gap-checks.md` for commands and exact coverage.
