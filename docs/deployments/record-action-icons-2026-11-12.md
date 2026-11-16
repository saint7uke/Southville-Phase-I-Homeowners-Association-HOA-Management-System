# Record-action icons — SSH deployment, 12 November 2026

## Release

User-authorized SSH deployment completed at approximately **14:18 Asia/Manila**. This is the presentation-only record-action release, not a new business workflow or database migration.

- Release archive SHA-256: `c8ffdb8e207cdefe3db7edfb0fee2bb36b741dba98f4c4cd5da99a5832b26907`.
- **56 files**, including the complete matching production asset build. Production source differences were reviewed against the private backup; PHP syntax checks passed before installation.
- Private server release: `/home/u130796816/.hoa-deploy/record-icons-20261112-v1`.
- A brief maintenance window was used. Installed assets with the manifest last, retained old hashed assets, rebuilt config/route/view caches, and returned the application live. No migration command was run.

## Backups and rollback

- Verified source/build backup: **`before-clean.tar.gz`**, 2,556,020 bytes, SHA-256 `b6011ff88de84e4ca0e259c7dee80b334ecc776d056eee67f7c0fbf56d79aa87`. Extracted privately on the server and checked against the pre-deployment file fingerprints: zero mismatches. A matching copy was downloaded privately to the local release directory. Use this clean archive rather than the initial superseded `before.tar.gz`.
- Single-transaction database backup: **`database.sql`**, 1,116,895 bytes, SHA-256 `f456b256ffd587d76e3c57f68b427bc3c2bca90065bb52c5349d2ea4a4e6f722b`. Stored only outside the webroot; it was not downloaded or restored. Database and source archives have mode 0600.
- Roll back application presentation by restoring affected source and the matching build from `before-clean.tar.gz`, then rebuilding Laravel caches. New helper classes can remain unused after restoring the prior provider/page files. Do not automatically restore the database: this release changes no schema or business data.

## Production verification

- Deployed manifest: **56 verified files, zero mismatches**.
- `.env`, root `.htaccess` and the readable user-crontab fingerprint match their pre-deployment values. No SMS settings, credentials, upload settings, worker or scheduler configuration was changed.
- Fingerprints unchanged for projects/images, households/members, service requests, users/homeowners, payments, dues obligations, certificates, contact messages, email deliveries, system settings and audit logs.
- Queue rows unchanged: **73 queued jobs, six failed jobs**. No workers were started/restarted and no jobs were retried, cleared or processed by deployment.
- SMS deliveries remain six with their substantive-column fingerprints unchanged. The SMS comparison intentionally excludes only `updated_at`, because the already-running history reconciliation can refresh it; it still covers statuses, body/phone snapshots, identities, provider fields and attempt counts.
- Baseline counts include six obligations and 1,209 audit entries. Those already existed before this deployment; deployment did not create them.
- `/up`, landing page, registration and Admin/Staff/Homeowner login endpoints returned HTTP 200. Sign-in remains text-labeled.
- Referenced public/login build resources returned HTTP 200. Both the application and Filament stylesheets returned HTTP 200 and contain the new scoped record-icon rules. New translation and locale bundles are accessible.
- Production checks were read-only with respect to business records. No live login, record action, official certificate operation, email/SMS send or enrollment was performed. Authenticated icon interactions, permission boundaries, mobile targets and confirmation/cancellation evidence come from the isolated local regression/browser runs documented in the audit, not a claimed production write-based smoke test.

## Local evidence

See `docs/audits/record-action-icons-2026-11-12.md` for the action inventory, four passing desktop/mobile Chromium checks, PHP/tooling tests, prerequisite-dependent font rerun, corrected stale pickup-policy assertions, formatting and build results. This release does not establish full-system certification or live notification delivery.
