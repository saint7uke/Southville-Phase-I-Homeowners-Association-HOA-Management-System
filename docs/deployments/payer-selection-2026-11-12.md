# Actual payer selection — SSH deployment

Deployed after separate user authorization on **12 November 2026, approximately 23:03 Manila / 15:03 UTC**. No migration, business-record correction or production payment submission was performed.

## Release and backups

- Private server directory: `/home/u130796816/.hoa-deploy/payer-selection-20261112-v1` (0700).
- Source/build backup `before.tar.gz`: **3,966,618 bytes**. Database backup `database.sql`: **1,167,660 bytes**, SHA-256 `eb802b675ae90de6baedba34f8d633314eb4d15b6147a7c25f45413906d7b5ba`. Both backups are 0600 and outside the public web root; the database was not downloaded.
- Explicit allowlist archive: **32 files**, SHA-256 `71a97fe8cf804bb65adb7386567d07b880aecfb289610121558744309702a57a`. Six application/translation files plus 26 matching build files; no tests, credentials, fonts, migrations or private documents.
- Compared existing form/action/page/translation files against the source backup. Differences matched the shared payer-control fix. Production `PropertyDuesAccounts`, `RecordPayment` and `RecordAdditionalPayment` hashes exactly matched the tested local dependencies; these were not replaced. JavaScript filename changes follow the translated chunk dependency change.

## Installation and evidence

- Verified the uploaded archive checksum and all staged-file hashes before installation. PHP syntax checks passed.
- Briefly held the existing SMS worker and maintenance locks without invoking either processor. Used a short maintenance window; installed the new shared field before consumers and the build manifest last. Refreshed compiled views with `artisan view:cache`, then brought the application online.
- **All 32 deployed checksums matched**. Final installation table fingerprints matched the installation baseline, including migrations, payments, dues obligations, receipts/previews, audit records, notification tracking and settings.
- Existing SMS housekeeping updated `sms_batches` and `sms_verifications` between backup and installation, with counts unchanged. Preserved those live operational values; refreshed only those two baseline fingerprints under the SMS locks, retained `before-state-original.json`, and recorded `operational-drift.json`. No other table drift was accepted, and no operational/business data was restored or rewritten by deployment.
- Baseline and final counts: **five payments, 11 dues obligations, one payment document, 73 queued jobs and six failed jobs**. These are this release's current baseline, not the earlier installment deployment's counts. No historical rows were reverted.
- `.env`, root `.htaccess`, configuration cache, route cache and SMS worker wrapper fingerprints were unchanged. No migrations, configuration changes, cron edits, worker starts or queue retries occurred.
- Read-only smoke checks: `/up`, `/login`, `/homeowner/register` and new translation/panel JavaScript assets returned **200**. Unauthenticated Admin/Staff payment-create routes returned **302** to authentication.
- Confirmed the shared field autoloads against the production Filament installation, native selection and validation hook are available, both language messages resolve, the previous settlement schema remains present, and maintenance mode is off.
- Evidence is retained privately as `verification.json`, `smoke.json`, syntax/view-cache/maintenance logs, release manifest and backups. Local audit/testing: `../audits/payment-payer-selection.md`.

Production forms were not submitted, and no live resident records were edited or used as synthetic tests. No notifications were sent by deployment. Existing payer eligibility remains enforced: an account/property that has no eligible payer now requires HOA review rather than automatic activation, identity repair or relaxed validation. Actual authenticated production interactions remain unverified without designated test records.

## Rollback

Restore the affected source and previous asset manifest/build from the private source backup, remove only the newly introduced `app/Filament/Resources/Payments/Schemas/PaymentPayerSelect.php` after restoring its consumers, and refresh compiled views. Preserve business files, configuration and financial data. No database restore or migration rollback is needed. Keep cached asset files available for already-open browser sessions; do not delete broad directories or process queued/failed jobs as part of rollback.
