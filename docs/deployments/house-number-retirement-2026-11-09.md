# House Number retirement deployment — 2026-11-09

## Implementation and verification

See the [dependency audit](../audits/house-number-retirement.md) and [reviewed source manifest](house-number-retirement-2026-11-09-manifest.txt).

- Removed the remaining shared Admin/Staff Add/Edit Household field; current address displays and report/table searches omit House Number.
- New financial/request/certificate snapshots and document preparation omit it after preserving authoritative identity. Existing stored values, historical rendering, issued files and financial associations remain unchanged.
- Fixed stale/crafted edit handling, visible conflict errors, historical-key preservation and linked legacy Owner/Co-owner validation when a new household has a null house number.
- Focused retirement tests: eight tests / 46 assertions passed.
- Main household/head/address, receipt, certificate, preview-approval, tenant-payment and historical-snapshot run: 187 tests / 1,131 assertions; no failures, 15 Cohabitation font-prerequisite skips. This run included six retirement cases before two additional focused cases were added.
- Final profile/receipt/Residency/retirement run: 76 tests / 509 assertions passed. Report/export, confirmed-identity, payment, landlord, summary and RBAC run: 88 tests / 721 assertions passed. Focused PhaseThree household regressions: five tests / 38 assertions passed.
- Initial unrelated project-image 404 passed unchanged in a standalone rerun (one test / nine assertions). An initial overlapping PDF setup error was absent in the later combined document run. No project or certificate-validation bypass was added.
- Five Chromium scenarios passed: Admin ordinary save, Staff ordinary save, member-driven head assignment/cancellation, household-driven replacement, validation/no-success and confirmed clearing. Actual dialogs show Block/Lot/Phase and no House Number. Filament display-contents dialog containers are tested through visible child controls rather than an empty outer bounding box.
- Fourteen JavaScript tooling tests, production Vite build, targeted Pint and whitespace checks passed. Existing public-image build warnings remain unrelated.

## Hostinger rollout

- Used host-key-verified SSH. Confirmed matching Laravel/Filament/Livewire versions and no unreviewed production source differences.
- Read-only schema check confirmed both household and homeowner House Number columns are nullable; migration count 47. No migration was run.
- Installed 17 reviewed PHP source files and the complete matching 27-file asset build (44 files). All uploaded and installed SHA-256 values matched; 17 PHP files passed server syntax checks before installation.
- Private release/backup directory: `/home/u130796816/.hoa-deploy/house-number-retirement-20261109-v2` (0700).
- Private source backup: `source-backup.tar.gz`, 1,773,593 bytes. Database backup: `database.sql`, 994,144 bytes. Backup files are 0600. The earlier v1 directory is staging only and was never installed.
- Held the existing SMS worker and maintenance file locks, briefly entered maintenance mode, atomically installed files with manifest last, rebuilt Laravel configuration/Blade caches, and returned the application to live mode.
- All 30 checked tables retained complete row fingerprints during installation. Preserved 67 queued jobs, six failed jobs, migration records, financial records, memberships, SMS/email tracking, household-head assignments and existing document records.
- Production `.env`, root `.htaccess` and private SMS worker wrapper checksums were unchanged. No global scheduler, queue processing, SMS send, account approval, certificate issuance, record cleanup or historical regrouping was performed.

## Final read-only production verification

- All 17 installed source files match the reviewed local versions; the public build manifest matches the complete local build.
- Server-side schema inspection of the shared household form confirms House Number is absent; current formatter output excludes a synthetic retired value without writing a record.
- `/up` and `/homeowner/register`: HTTP 200. Guest access to `/admin/households` and `/staff/households`: HTTP 302 to authentication, preserving access restrictions.
- Maintenance off; SMS remains enabled, test mode off and cap 50. Queue counts remain 67 waiting/six failed. Request 5 remains Completed and household 2 head pointer remains member 1.
- No authenticated production save, certificate generation or SMS send was used for verification. Local synthetic browser saves establish actual dialog behavior; live checks establish deployed source, assets, schema and access responses.
