# SMS input/UI deployment — 2026-11-09

Status: deployed to Hostinger over host-key-verified SSH. The site is live.

## Installed and backed up

- Installed 22 reviewed source files and the complete matching 27-file Vite build (49 files). See [source manifest](sms-input-ui-2026-11-09-manifest.txt).
- All existing production sources matched known deployment/Git baselines before installation; one new scoped SMS stylesheet was added. No unreviewed server changes were overwritten.
- Uploaded files passed SHA-256 verification; 11 non-view PHP files passed server-side syntax checks. Installed checksums matched afterward.
- Private backup directory: `/home/u130796816/.hoa-deploy/sms-input-ui-20261109-v1` (0700). Source/config backup: `source-backup.tar.gz`, 1,411,109 bytes. Database backup: `database.sql`, 969,049 bytes. Backup files are 0600 and outside the public site.
- Held the existing SMS worker and maintenance file locks during deployment. Used brief application maintenance mode, atomic file replacement with the asset manifest last, then rebuilt configuration and Blade caches. Returned the application to live mode.
- No migration, dependency installation, environment update, global cache flush, queue processing, scheduler change or test SMS was performed by the deployment.

## Preservation checks

All 30 checked tables retained their row counts and complete row fingerprints during the locked installation, including business records, SMS tracking, email tracking, migrations, settings, queued jobs and failed jobs.

- Queued jobs: 64, unchanged.
- Failed jobs: six, unchanged.
- Production request 5: Completed, unchanged.
- Household 2 head pointer: member 1, unchanged; this deployment performs no household reconciliation.
- Production `.env`, root `.htaccess` and private SMS worker wrapper hashes: unchanged.
- SMS remains enabled in environment and database, test mode off, daily cap 50. No resident enrollment or historical replay was performed.

## Live verification

- `/up`, `/login` and `/homeowner/register`: HTTP 200.
- Registration includes the strict `09[0-9]{9}` pattern, 11-character mobile limit, numeric-input marker, destination/Edit link and scoped SMS layout.
- Public application JS, Filament adapter JS, shared SMS JS and SMS CSS: HTTP 200; downloaded SHA-256 values match the reviewed local build.
- Fresh server bootstrap reports maintenance off and all 22 deployed sources identical to the local reviewed versions.

Production verification was read-only: no account creation, OTP request, consent change, payment/complaint transition or announcement send. Local evidence (216 distinct PHP tests, 14 tooling tests, six Chromium scenarios plus the final two-case registration rerun) is detailed in the [SMS audit](../audits/textbee-sms-integration.md). Production end-to-end OTP/handset receipt for this UI update was not exercised.
