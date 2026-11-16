# Installment payments release — deployed 12 November 2026

## Release inventory

- Payment entry/history: `RecordAdditionalPayment`, `InstallmentActions`, payment table and its two new Blade dialog views.
- Settlement eligibility/issuance: `ButawReceiptEligibility`, `ButawReceipts`, `ButawReceiptSource`, `PaymentReceiptResolver`, `PaymentSettlementProtection`.
- Compatibility/locking: `Payment`, `DuesObligation`, `ConfirmReceiptDetails`, `UpdatePaymentMetadata`.
- Presentation: detailed payment PDF wording, receipt actions, English/Filipino dictionaries and matching Vite assets/manifest.
- Additive migration: `2026_11_12_000001_add_monthly_settlement_receipt_identity.php`. It adds a nullable unique foreign key; existing rows remain null. No old payment/receipt rows are updated.
- Verification-only changes: focused feature/concurrency tests, isolated browser fixture/spec and runner options; these do not need production upload.

See `docs/audits/installment-payments-and-settlement-receipts.md` for evidence and commands.

## Separate authorized SSH rollout

1. Compare reviewed release files and production migration state/schema. Record current queued/failed-job counts and protected configuration checksums without processing jobs.
2. Create private source/database backups outside the public web root. Preserve `.env`, `.htaccess`, font paths, source approvals, SMS configuration, cron, households and financial/document records.
3. Install the reviewed additive migration before enabling the new application source; use a controlled maintenance window to avoid old-generation requests during the transition. Generation fails closed when the settlement schema is unavailable; saved historical files remain readable.
4. Deploy only reviewed application/templates/translations plus matching assets, with the asset manifest last; rebuild appropriate Laravel view/configuration caches without changing configuration values. Do not start workers/scheduler or resend notifications.
5. Verify file checksums, migration status, application health and read-only access behavior. Do not create payments, approve proofs, issue receipts or use real resident records as tests without separately approved test records.

## Rollback

Restore the private source/assets backup and refresh caches. Retain the additive nullable column if any settlement documents have been issued; never roll back financial records, delete documents or remove settlement identities to work around a release issue. Remove only the new application files introduced by this release when fully reverting source; keep business files and documents. Do not restore the database merely to roll back application source.

## Authorized SSH deployment

After separate user authorization, deployed over authenticated SSH on 12 November 2026, approximately 21:04 Manila / 13:04 UTC.

- Private staging/backup directory: `/home/u130796816/.hoa-deploy/installments-20261112-v1` (0700). Source/build backup `before.tar.gz`: **3,743,575 bytes**. Database backup `database.sql`: **1,144,964 bytes**, SHA-256 `dacb182860aad36c2042467d5cccf4e9af212898eee2d98f38c99440b3c719c8`. Backups are 0600 and outside the public web root. The database was not downloaded locally.
- Explicit allowlist package: **46 files**, archive SHA-256 `c0e455c218124cb71c7898e4ff66df652f751b7c0c133cda722be0cf73864ce8`. Created by `node scripts/package-installments.mjs`; includes 20 source/template/translation/migration files and 26 matching build files. No credentials, fonts, tests or private documents were packaged.
- Reviewed production source differences against the private source backup. Existing `RecordPayment`, `ReviewPaymentProof`, `ChangePaymentDeletionState` and `PaymentDocument` dependency hashes exactly matched local tested versions; these dependencies required no upload. Other JavaScript filename changes follow the shared translation chunk hash change.
- First SSH authentication attempts without credentials were rejected. Password-authenticated SSH subsequently succeeded. SFTP stalled before transferring any files; SSH-stream upload succeeded and the full archive checksum was verified before extraction.
- A pre-installation fingerprint check correctly refused to proceed after existing SMS housekeeping changed `sms_batches` and `sms_verifications` (counts unchanged). No application installation or migration had occurred at that point. Preserved those live operational changes, retained the original fingerprint baseline in `before-state-original.json`, and recorded the differences in `operational-drift.json`. Briefly acquired the existing SMS worker/maintenance locks while capturing the final baseline and installing; did not invoke either processor or change their configuration. All other table fingerprints remained unchanged.
- Used a brief maintenance window. Applied **only** `2026_11_12_000001_add_monthly_settlement_receipt_identity`; its nullable unique column and foreign key were verified. Existing payment-document rows remain null; **zero new settlement documents** were created. No other migration ran.
- Installed reviewed source/assets with the manifest last; all **46 deployed checksums matched**. PHP syntax checks and `artisan view:cache` passed. Restored the application online. No dependency, route-cache, configuration-cache or environment change was needed.
- Final installation verification: no business-table fingerprint changes, `.env` and root `.htaccess` hashes unchanged, **73 queued / six failed jobs unchanged**. Existing four payments, 11 dues obligations, one payment document, household assignments, certificates, source approvals, notification tracking and settings were preserved. The existing receipt file matched its saved checksum.
- Read-only smoke checks: `/up`, `/login`, `/homeowner/register` and new translation/panel JavaScript assets returned **200**. Unauthenticated `/admin/payments` and `/homeowner/payments` returned **302** to authentication. New classes autoloaded; receipt schema readiness, unique settlement index and foreign key passed. Private evidence: `verification.json`, `smoke.json`, migration/view-cache logs and source/database backups.
- No production payment entry, proof approval, receipt generation, notification send, queue processing, test-account creation or historical backfill was performed. Actual production payment interactions were not exercised without designated test records; isolated local tests remain the workflow evidence.
- Cron definitions were not altered. `crontab` listing is unavailable through this SSH account, so an independent hPanel definition comparison is not claimed. Existing wrapper and runtime configuration files were not deployed or edited.
