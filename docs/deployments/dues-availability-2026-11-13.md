# Clear dues availability — SSH release

Installed on **13 November 2026, 09:41:31 Manila / 01:41:31 UTC** after user authorization and local workflow/availability verification. No migration or account correction.

## Reviewed release

- Private directory: `/home/u130796816/.hoa-deploy/dues-availability-20261113-v1` (0700).
- Source/build backup: `before.tar.gz`, **4,197,515 bytes** (0600). Database backup: `database.sql`, **1,113,338 bytes** (0600), SHA-256 `c430c584e03621ec2db13a44093c926fe6146f8ccd62c77871f0f01728b3319c`. Database backup remains on the server.
- Confirmed the temporary database credential file was removed after backup.
- Release: **31 files** (five runtime/translation files and 26 build files), archive SHA-256 `2d6018057530bdbfe2b5282517f2aa8fb816bf8ed8c3f75f17387fe5e1b9db24`.
- Compared both existing payment controls and EN/FIL translations against the private source backup. Only intended availability/guidance changes and six new translation entries were present. Checked the new read-only service separately.
- Eight unchanged dependencies matched tested local hashes: payer policy, both recording actions, homeowner resource/policy, actor/access helpers and obligation model.
- Built JavaScript entry content matched previous production after normalizing dependency filenames. CSS rule comparison found only the standard `.visible{visibility:visible}` utility added, with no removed rules.

## Installation checks

Verified uploaded archive/staged hashes and PHP syntax. Briefly held existing SMS worker/maintenance locks without invoking processors. Used a short maintenance window, installed the new service before consumers and the manifest last, then refreshed compiled views. Kept existing hashed assets available.

**All 31 deployed checksums matched.** The installation verification reported no changed table fingerprints and unchanged protected files: `.env`, root `.htaccess`, configuration cache, route cache and SMS worker wrapper. Maintenance mode is off; the service autoloads, native disabled-option support exists, both languages resolve and the existing settlement schema remains present.

Baseline/final counts: **five payments, 11 dues obligations, one payment document, 73 queued jobs and six failed jobs**. Existing homeowner archive state, household assignments, financial snapshots, certificate records and notification settings were not modified. No migration, notification send, production payment, worker start, queue replay or cron edit.

Private evidence: release manifest, `before-state.json`, `verification.json`, `smoke.json`, syntax/view-cache/maintenance logs and backups. Local evidence/package: `C:/Users/bargo/AppData/Local/Temp/hoa-dues-availability-release-62xaua`. The packaging manifest's original `deployed: false` is a packaging-time marker; installation evidence establishes the later rollout.

Read-only HTTP checks passed: `/up`, `/login`, `/homeowner/register`, new locale/translation JavaScript and panel CSS returned **200**. Unauthenticated Admin/Staff payment-create pages returned **302** to authentication. Authenticated production form submissions are deliberately not exercised.

Existing SMS housekeeping changed `sms_batches` and `sms_verifications` between backup and installation, with counts unchanged. Preserved those live values and refreshed only their two fingerprints under the existing SMS locks. The original baseline and `operational-drift.json` remain private; no other drift was accepted. Installation-time fingerprints matched afterward. No SMS data was restored or rewritten by this deployment. Cron definitions were not changed; hPanel schedule definitions were not inspected through SSH.

## Rollback

Restore the affected original source files and previous build manifest/assets from `before.tar.gz`, then remove only the new `app/Services/PaymentDuesAvailability.php` after restoring its consumers. Refresh compiled views and verify read-only health. Keep hashed assets needed by open sessions; do not recursively delete build/storage directories. Do not restore the database, alter account records, roll back migrations, change configuration or process queues.

Unavailable dues require authorized HOA review; deployment does not restore archived homeowners or relax payer eligibility. Local verification details: [audit](../audits/payment-dues-availability.md).

Verification caveat: 201 PHP cases across SQLite and disposable MariaDB, 21 tooling tests, the production build and both focused availability browser widths passed. The full mobile installment browser flow passed; an extended desktop rerun passed settlement/history but timed out at the shared language switch and is explicitly not counted as a full pass. No live production payment or notification was used to investigate that separate timing issue.
