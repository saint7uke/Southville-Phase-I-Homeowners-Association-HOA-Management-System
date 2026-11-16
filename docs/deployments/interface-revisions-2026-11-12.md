# Interface and resident workflow revisions — 12 November 2026

## Local implementation

Public registration no longer offers SMS enrollment. Registration HTTP and domain validation reject consent/proof/verification-state payloads; guest OTP endpoints deny registration scope. Primary mobile validation and authenticated profile enrollment remain in place. The Important Reminder and required privacy consent use accessible HTML and a scoped light-blue layout. The public Contact section now provides office details and resident sign-in; Why it matters and its navigation anchor are removed.

Operational consequence: new applicants start unenrolled and cannot receive an initial approval SMS before they have verified/enrolled through an Active account's profile. Registration/approval email remains governed by the existing email policy. Profile enrollment authorizes future eligible SMS events only; it does not replay initial approval or other historical events. Existing verified/consenting preferences are preserved, and no resident is automatically enrolled.

My Concerns combines resident complaints and inquiries with separate underlying records and existing workflows. New inquiries belong to the authenticated sender; legacy email matches never imply ownership. Listings use a paginated database UNION and type-qualified detail identifiers. Only transport-accepted outbound inquiry replies appear; internal notes and failure diagnostics are excluded. Both the Filament panel and legacy portal use the same ownership service. Complaint subjects derive from category/specification and resident priority defaults to Medium. Existing details and attachments remain protected.

The Certificates request page provides pickup tracking. Resident PDF actions, signed download URLs and preview endpoints deny resident access; notifications point to tracking. Existing standalone certificate metadata remains visible. Managers retain printing/download access. Ready for pickup requires an issued, unexpired certificate and explicit signature/dry-seal confirmation; collection requires readiness. Both transitions are locked, audited and do not send new notifications.

Management screens have compact actions and fewer default columns. Audit detail dialogs retain accountability and technical data. Household head review is unchanged; assignment is shorter and household secondary actions use a menu. User status choices come from the existing transition policy; homeowner approval no longer updates the resident before the account action succeeds. Reports start with one report selector. SMS history shows readable purposes with hidden technical failure columns. Roles remain authorized but are absent from routine navigation. Profile notification settings collapse, existing photos open the file chooser, and password saves use the primary color. The default account-menu logout remains; the redundant bottom account widget is removed.

Planned project dates may be future dates. Other statuses retain today-or-earlier dates. Five images remain the maximum, now 15 MB each in form, domain and Livewire temporary validation. Publication remains Completed-only.

Annual Dues shows all twelve months, exact charges/penalties, accepted paid amounts, pending payment amounts, balances and totals. Missing assessments are labeled Not assessed. Residents are scoped to their own authorized property account; management has a homeowner selector. Prepare billing month creates/reuses one applicable monthly assessment, then existing payment recording selects that obligation. Payment date is independent of billing month. Future years are rejected; historical obligations and rates are preserved. Payments remain one record per obligation. Cash-only submission policy is unchanged. Future billing months do not block certificate eligibility.

## Additive schema

`2026_11_12_000001_add_resident_concerns_and_certificate_collection` adds nullable `contact_messages.user_id`, `certificates.ready_for_pickup_at`, `ready_for_pickup_by`, `collected_at`, and `collected_by`. It performs no backfill, reassignment, document regeneration, or business-record cleanup. Existing inquiry ownership and pickup timestamps remain unknown until an authorized operation records them.

## Regression finding corrected

The genuine-Arial run exposed an existing Cohabitation review-preview defect: `ResidencyCertificatePreviews::context` chose an approved source whenever row locking was enabled, including draft preparation. Stage/review now chooses the existing authorized draft path; issuance still chooses the approved source under locks. Source confirmation, printed-year/signatory checks, watermarking, fingerprint checks and actor ownership remain enforced. The existing before-confirmation, overflow, staff permission and JSON-fingerprint cases now pass without weakening assertions.

## Verification evidence

- Broad isolated workflow run: **243 passed / 2,199 assertions**, four environment-dependent skips in that command.
- Guarded disposable loopback MariaDB runner: **6 passed / 38 assertions**, covering the three tenant-payment races and three certificate/payment races. New additive migration also applied successfully there.
- Explicit licensed local Arial run after the preview correction: **58 passed / 280 assertions**, no skips. Uses synthetic sources; no official source was approved.
- Focused report/project/new-workflow run: **34 passed / 252 assertions**.
- Additional reporting, original Residency, SMS and commit-boundary run: **98 passed / 518 assertions**; the twelve Arial-dependent cases skipped there are covered by the explicit-font run above.
- Tooling: **16 passed**.
- Additional address/profile/landlord/payment/mail/role regressions: **82 passed / 679 assertions**.
- Final concern/household/report/notification regressions: **64 passed / 317 assertions**. Legacy portal/new concern verification: **26 passed / 152 assertions**.
- New implementation acceptance file, including forged annual-account selection and Staff permission revocation: **10 passed / 86 assertions**.
- Desktop/mobile resident browser checks: **2 passed**, including live English/Filipino switching, preserved first-name input, unchanged resident-entered inquiry text, light-blue reminder CSS, required unchecked privacy consent, concern submission, annual dues and profile notification controls. Final artifacts: `C:/Users/bargo/AppData/Local/Temp/hoa-alert-browser-interface-Pm4dzt`.
- Desktop/mobile administrator browser checks: **2 passed**, including actual billing-month selection, cancel/confirm pickup readiness, report selection and compact household layouts. Artifacts: `C:/Users/bargo/AppData/Local/Temp/hoa-alert-browser-interface-hx16SQ`.
- Browser setup defects corrected during verification: select Owner before inspecting registration fields; scope custom select choices to their visible listbox; use the existing SweetAlert confirmation for pickup actions. No runtime controls were bypassed to pass these checks.
- Screenshots visually reviewed at desktop/mobile widths. Household tables fit the ordinary desktop viewport; narrow phones retain the existing accessible internal table scroll region. Remote avatar images are deliberately blocked by the isolated browser allowlist.
- Pint passed the touched PHP files; JavaScript syntax and `git diff --check` passed. Production build passed with **130 modules / 27 asset files**. The two existing runtime hero-image URL warnings remain; no new CSS import warnings remain.
- Counts above overlap; do not add them into a unique suite total. No intended-environment check remains skipped: the MariaDB and Arial runs cover their corresponding default-command skips.

Commands:

```powershell
php artisan test --compact --filter='InterfaceRevisionTest|ProjectPublicationTest|SmsEnrollmentHttpTest|ServiceRequestPreviewApprovalTest|CohabitationRequestGuidanceTest|CertificateDuesEligibilityTest|TenantPropertyPaymentsTest|DuesAndPaymentManagementTest|EmailReliabilityTest|EmailBrandingTest|HouseholdHeadAssignmentTest|ServiceRequestEditingTest|AccountLifecycleTest|WorkflowNotificationTest|PortalWorkflowTest'
$env:CERTIFICATE_COHABITATION_FONT = 'C:/Windows/Fonts/arial.ttf'
php artisan test --compact --filter='OriginalCohabitationCertificatesTest|IntegratedResidencyCertificatesTest|CertificateDuesEligibilityTest' --display-skipped
node scripts/run-readiness-gap-checks.mjs --mysql-only
node scripts/run-interface-browser-checks.mjs
npm.cmd run test:tooling
npm.cmd run build
node scripts/package-interface-revisions.mjs
```

Browser fixtures, database, key, sessions and storage are private temporary files. Mail uses array transport and SMS is disabled. External requests are blocked; a missing remote avatar in these screenshots does not establish a production avatar defect. Desktop/mobile checks cover registration, concern submission, optional profile enrollment visibility, certificate pickup notice, annual dues and management dialogs. No actual email/handset delivery is claimed.

## Hosting prerequisites and separate rollout

Not deployed by this implementation task. The package script uses an explicit runtime allowlist plus the complete build and emits file checksums. It excludes `.env`, `.htaccess`, customer data, proprietary certificate fonts, official certificate sources and tests. Existing workspace changes were preserved; compare every affected deployed file before replacing it.

1. Inspect production differences, pending migrations, effective PHP/FPM and proxy upload limits, and Laravel/Livewire caches using read-only checks. Check font identity privately; do not upload fonts.
2. Back up the affected source/build and database to a private directory outside the webroot. Record current migration state and protected-file hashes. Do not start queues or the global scheduler.
3. Support at least 15 MB per file. For a request carrying five such files, allow appropriate request overhead: recommended `upload_max_filesize >= 16M`, `post_max_size >= 80M`, `max_file_uploads >= 5`, plus matching proxy limits. Local CLI currently reports 40M per file, 40M per request, 20 files; it cannot establish Hostinger web-SAPI limits or guarantee five simultaneous full-sized uploads. Hosting changes need separate review.
4. Stage the reviewed source and complete assets privately; use a brief maintenance window for the additive schema/application transition. Inspect outstanding migrations and apply only the reviewed pending migration(s), not an unreviewed `migrate --force` batch. Install the asset manifest last and retain old hashed assets for open pages.
5. Rebuild the existing deployment's Laravel config/route/view caches without changing environment values. Verify source/build checksums and additive columns. Confirm guest contact/SMS endpoints deny access, public office details render, and resident file URLs remain forbidden. Use designated synthetic records for any write-based smoke tests.
6. On rollback restore the backed-up application and matching assets. Leave the additive nullable columns in place; do not drop newly recorded inquiry ownership or pickup data.

Production `.env`, `.htaccess`, SMS configuration, cron jobs, queued/failed jobs, household assignments, financial records and issued files are untouched. Property allowlisting remains deferred pending the HOA's approved Phase/Block/Lot list. No live notifications, official issuance, production pickup updates, or historical-job replay were performed.

## Prepared release

- Archive: `C:/Users/bargo/AppData/Local/Temp/hoa-interface-release-J4DWrg/release.tar.gz`
- SHA-256: `85165ab2df576cc2226ea3736e9bf9ccd168c762a32cc6579ab3619222e4a7d2`
- Manifest: `C:/Users/bargo/AppData/Local/Temp/hoa-interface-release-J4DWrg/release-manifest.json`
- **110 files**, including complete matching assets. Manifest comparison against the final runtime workspace returned zero mismatches. The manifest contains the exact deployment allowlist and individual hashes.
- Initial preparation state: prepared locally, before SSH authorization. This is superseded by the completed rollout recorded below.

## SSH rollout completed — 12 November 2026

The user separately authorized this deployment. The prepared archive above was transferred over SSH, its SHA-256 matched, and all **110 deployed files** matched the release manifest after installation. Production differences were reviewed against a private source backup. Staged PHP files passed syntax checks on production PHP 8.3.36.

- Private release/backups: `/home/u130796816/.hoa-deploy/interface-revisions-20261112-v1`, outside the webroot. Source/build archive and a single-transaction database dump were created before installation, with private permissions. Database dump: 1,093,406 bytes; SHA-256 `a7038400122067d580231fcf44c27ac75d688d77f5bd66fbd4be486a8840d5672`. A source-backup archive was also downloaded privately; no customer database was downloaded.
- Used a brief maintenance window. Applied only `2026_11_12_000001_add_resident_concerns_and_certificate_collection` (batch 14). Configuration, route and compiled-view caches rebuilt successfully; application returned to live mode.
- Verified the nullable inquiry-owner and certificate pickup schema. No historical ownership, pickup timestamps, approvals, certificates or payments were backfilled.
- Actual web PHP (`litespeed`) reports `upload_max_filesize=1536M`, `post_max_size=1536M`, `max_file_uploads=20`. No hosting configuration was changed. A temporary minimal upload-limit probe was removed immediately and subsequently returned 404. These configured limits satisfy the application requirement; an actual five-image/75 MB production upload was deliberately not performed, so proxy-level full-payload acceptance remains untested.
- HTTP: `/up`, landing page and registration returned 200. Registration contains Important Reminder and no SMS send-code control. With a valid guest CSRF session, both registration OTP request/confirmation returned 403 and `/contact` returned 401. All nine referenced public build resources checked returned 200. New annual-dues routes are registered for Admin, Staff and Homeowner; My Concerns is registered for Homeowner.
- `.env` and root `.htaccess` hashes unchanged. Business-table fingerprints unchanged for projects/images, households/members, service requests, users/homeowners, payments/obligations, certificates, contact messages, email deliveries, system settings and audit logs. Queued jobs remain **73** and failed jobs **6**, with their complete row fingerprints unchanged.
- SMS deliveries remained six records. The whole-table comparison detected background activity: a read-only comparison with the private pre-deployment dump isolated changes to **`updated_at` only on rows 3 and 5** (both still Unknown). No delivery status, message content, provider identifier, attempt count or other delivery column changed. Existing SMS processing was neither started nor reconfigured by deployment. Private `verification.json` and `sms-diff-result.json` retain this exception; it is not described as an entirely unchanged SMS table.
- No production email/SMS sends, queue replay, official issuance, pickup actions or business-record smoke writes were initiated. SMS settings, cron configuration, existing queued/failed jobs, proprietary fonts and official documents were not modified. Authenticated role/security and write-based workflow evidence remains the isolated local regression/browser coverage above, not a claim of production end-to-end testing.

Rollback: restore affected source and matching assets from `before.tar.gz`, rebuild Laravel caches and return the application live. Leave the additive nullable columns in place; do not roll back by dropping potentially newly populated inquiry ownership or pickup data. Preserve the private database backup for recovery review rather than automatically overwriting live data.
