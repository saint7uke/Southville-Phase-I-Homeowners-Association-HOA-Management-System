# TextBee SMS — SSH deployment, 9 November 2026

Deployed to `https://southvillehoams.com` through authenticated, host-key-verified SSH. The site is live after a brief maintenance window.

**Current status:** live SMS is enabled following the user's explicit approval, successful test-phone receipt and verified SMS-only cron processing. Test mode is off; verification/consent and the 50-segment/day cap remain enforced. The earlier disabled and test-only sections below record the preceding deployment phases.

## Installed and migrated

- Installed the 52 reviewed application/source paths in `textbee-sms-source-manifest.txt` plus the complete matching 27-file Vite build/manifest: **79 checksums matched** after installation.
- All 39 non-view PHP files passed server-side syntax checks before installation. Laravel 12.69.2, Filament Tables 4.13.4 and Livewire 3.8.8 matched locally; no dependencies were installed or upgraded.
- Applied only `2026_11_09_000002_add_textbee_sms.php` using a migration-specific `--path`, `--force` and `--no-interaction`. All prerequisite migrations were already applied.
- Verified nine new SMS tables exist and remain empty; all existing announcements have the default false Important flag, and complaint transition metadata remains null.
- Rebuilt optimized Composer autoloading without scripts, cleared Laravel caches, and rebuilt configuration and Blade caches.
- Compared production files against previous deployed baselines and the explicitly reviewed SMS-only differences. No unrelated deployed source changes were discarded.

## Disabled/test-only state

- Effective environment SMS switch: **off**.
- Database SMS master switch: **off**.
- Test mode: **on**; test-number allowlist: **empty**.
- No provider credentials were copied from the conversation or added to production configuration. Production `.env` remained unchanged.
- No SMS, OTP, test message, announcement blast, incoming synchronization or maintenance processing was initiated. No SMS worker, global scheduler, cron job or queue restart was started.
- Production database queue `retry_after` is 660 seconds, greater than the SMS job's 60-second timeout. This was inspected only; no queue configuration was changed.

## Preserved data

Compared exact row counts and SHA-256 row-data fingerprints before/after installation using each table's original columns. All matched:

- Users (10), homeowners (5), households (5), household members (8).
- Dues obligations (4), payments (1), complaints (6), service requests (6), certificates (8).
- Announcements (3), original system-setting fields (1), audit logs (956).
- Workflow email deliveries (0), announcement email blasts (3), announcement email deliveries (9).
- Contact messages (3), contact activities (6), administrator table preferences (1).
- **64 queued jobs and six failed jobs, including their exact existing contents.**

Production `.env` and root `.htaccess` hashes matched their pre-install values. No private documents/fonts were transferred. Request 5 remains Completed; all eight certificate records are unchanged. Household 2's head remains member 1, and its membership rows—including the unresolved Head-label conflict—were not modified or reconciled.

The migrations table gained only the reviewed SMS migration. No data backfill, destructive migration, database rollback or historical-event replay was performed.

## Private backups

Outside the document root:

`/home/u130796816/.hoa-deploy/textbee-sms-20261109-v1`

Contains `source-backup.tar.gz`, `database.sql`, a fresh maintenance-window `database-at-deployment.sql`, the reviewed package, checksums, baseline/deployment fingerprints, `verification.json` and `http-checks.json`. The directory was created mode 0700; database/source archives and metadata use mode 0600. Do not publish these archives or attach database dumps to support conversations. No rollback was required.

## Read-only production checks

| Check | Result |
| --- | --- |
| `/up`, `/login`, `/homeowner/register` | HTTP 200 |
| Registration SMS interface | Present, reports enrollment unavailable, existing registration remains available without SMS |
| Unauthenticated `/admin/sms-notifications`, `/admin/announcements` | HTTP 302 to authentication |
| `/staff/sms-notifications` | HTTP 404; no Staff gateway-management page registered |
| Current app and panel JavaScript assets | HTTP 200 |
| Source/build checksums | All 79 matched |
| Existing business rows and queue contents | Original-column fingerprints matched |
| SMS tables / test-only controls | Empty / disabled and empty allowlist |

Authenticated production create/edit/send controls were not exercised without designated isolated test accounts. Local evidence is recorded in `../audits/textbee-sms-integration.md`: 329 PHP tests, 12 tooling tests and four actual Chromium scenarios passed. These are not evidence of real carrier delivery.

## Activation remains separate

Revoke the exposed TextBee key, configure its replacement privately, and designate a consenting test number before authorizing controlled test sends. Device/SIM readiness, real API acceptance, carrier status and independently observed phone receipt remain unverified.

Any subsequent worker must consume only the dedicated `sms-otp` and `sms` queues. Enabling SMS-only maintenance or production sending requires separate approval. The prior decision not to process default/email queued or failed jobs remains in effect.

## Subsequent controlled test activation

After the user requested activation and supplied a replacement credential and consenting test number, configured production privately for one-number testing. This supersedes the disabled configuration above **only for the controlled test phase**:

- Environment and database master switches are on, test mode remains on, and the sole allowlisted number ends **5033**.
- All six resident workflow switches and incoming synchronization are off. No resident was automatically enrolled or marked verified.
- The key was transferred through authenticated SSH stdin and stored only in production private configuration/cache, not application source, this report or local `.env`.
- Backed up the prior environment/configuration and original business/queue fingerprints under private directory `/home/u130796816/.hoa-deploy/textbee-sms-test-20261109-v1` before changing configuration. Production `.env` was intentionally updated for this authorized activation; `.htaccess` and existing business records were unchanged.
- The device authenticated and was enabled; its reported SIM state was in service. Its last heartbeat was stale (approximately 220 minutes), so online readiness was not inferred from that metadata. The user was advised to open TextBee and reconnect the gateway phone.
- Submitted **one** fixed-content test through the existing SMS service: local batch 1, API acceptance at **2026-11-09 20:08:19 Asia/Manila**. Initial reconciliation reported **Dispatched**, with no provider Sent/Delivered timestamp yet. No repeat POST or automatic retry was initiated.
- The user subsequently confirmed **“Yes, received”** for the exact fixed test message on the allowlisted phone. This is actual user-confirmed test-phone receipt, not merely API acceptance. It does not establish delivery of every workflow/template or authorize importing older events.
- Exact fingerprints of original business/financial/document/membership tables and the **64 queued / six failed jobs** still matched after the test. Only SMS settings, the new SMS test/budget records and expected audit/cache entries changed.
- No SMS worker, maintenance cron or default/email processor was started. Broad resident sending remains off pending the separate live-processing step.

Because the replacement credential was shared in chat, privately rotate it as a security follow-up and update private configuration/cache without publishing it. No credential is included in this report.

### hPanel worker-command length workaround

The user authorized live activation, but recurring processing is still being configured through hPanel because `crontab` is unavailable through SSH. hPanel rejected the full worker command for exceeding 255 characters.

Installed private, mode-0700 wrapper `/home/u130796816/.hoa-deploy/run-sms-worker.sh` and validated it with `/bin/sh -n`. It uses the verified PHP 8.3 binary, a nonblocking worker lock, database queues **only** `sms-otp,sms`, `--stop-when-empty`, `--tries=1`, `--timeout=60`, `--max-time=45`, `--max-jobs=100` and `--sleep=1`. No worker was started during wrapper installation; the existing 64 queued/six failed jobs remained untouched.

Use **Custom**, every minute (all five schedule fields `*`), with this short command for the worker job:

```text
/bin/sh /home/u130796816/.hoa-deploy/run-sms-worker.sh
```

This replaces only the second/worker command. Cron creation and actual recurring execution still require verification before declaring production activation complete. The first SMS maintenance job remains separate; no global Laravel scheduler or default/email worker should be added.

## Live activation completed — 9 November 2026, Asia/Manila

The user explicitly approved live SMS and SMS-only processing, then confirmed saving the two hPanel cron jobs. Completed and verified:

- The SMS maintenance lock exists and maintenance updated the provider-check timestamp recently (observed **20:47:02 Asia/Manila** during final verification).
- Enqueued one SMS-queue verification job referencing the already-accepted test batch. Its handler returns immediately because the batch is not Queued: this checks worker execution **without submitting another SMS**. The cron worker consumed the probe, no new failed job appeared, and original queue fingerprints still matched. No worker was manually invoked to make the cron verification pass.
- Enabled the environment/database master switches and all six workflow switches; set `TEXTBEE_TEST_MODE=false` and cleared the test allowlist. Kept the existing private key/device configuration, independent email settings, daily cap of 50 estimated segments, three-day advance reminders and weekly overdue frequency unchanged.
- Acquired the SMS worker lock while atomically updating private environment configuration and rebuilding Laravel's configuration cache, preventing a worker from starting with the old test-only configuration during the switch.
- Incoming SMS synchronization remains **off**, as originally selected. No default/global scheduler, default/email worker, historical event replay or manual Important announcement blast was started.
- Final verification confirmed **64 original default-queue jobs and six failed jobs**, with exact contents unchanged, and original business/financial/document/membership rows unchanged. The temporary SMS probe was consumed; it did not remain in the queue.
- No residents were enrolled or marked verified. At activation, there were **zero SMS contact-preference records**. Residents must verify their primary number and explicitly choose SMS consent before workflow notifications are eligible. Important announcement consent remains separate; publication alone does not send SMS.
- Public registration returned HTTP 200 with optional SMS enrollment present and no service-unavailable warning after activation.

Private activation backups, settings snapshots, probe metadata and verification results are under:

`/home/u130796816/.hoa-deploy/textbee-sms-live-20261109-v1`

The fixed test message was physically received according to the user's confirmation. Subsequent provider reconciliation reports Unknown for that test delivery, so provider-reported Delivered is **not** claimed or manually manufactured. There was no resend. Actual receipt of each of the six business workflows remains a separate end-to-end verification item; activation proves configuration and SMS-only processing, not universal delivery.
