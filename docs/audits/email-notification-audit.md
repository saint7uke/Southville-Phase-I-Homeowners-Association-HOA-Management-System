# Email notification audit and UI reliability changes

Date: 2026-11-09 (Asia/Manila). Initial implementation/automated verification was local. The reviewed release was subsequently deployed over SSH; see the [deployment record](../deployments/ui-email-2026-11-09.md). Real inbox delivery remains unverified.

## Evidence boundary

Code inspection, isolated SQLite/PHP tests, array/fake-mailer tests and browser checks do not prove SMTP authentication or inbox delivery. No real email has been sent for this audit. A designated test mailbox is still required. Gmail/Outlook visual compatibility, spam placement, sender alignment, production worker/cron execution and provider delivery logs remain unverified.

Sanitized effective local configuration: Gmail SMTP, `smtp.gmail.com:587`, `MAIL_SCHEME=smtp`, 30-second transport timeout, database queue, `retry_after=660`. Existing worker/mail-delivery attestations are false and were not changed. No credentials, production environment variables or account records were modified.

## Notification inventory

| Module | Email trigger | Status | Action taken |
|---|---|---|---|
| Public registration | Application received | Added; local automated coverage | One durable applicant confirmation after commit. Pending-review instructions, no activation or invitation claim. Admin-created pending accounts do not receive it. |
| Registration review | New applicant requires approval | Updated; local automated coverage | Active non-deleted administrators with registration-review permission and usable email only. Stable registration/recipient identity prevents repeated dispatch. |
| User management | Initial approval/rejection, activation, deactivation, suspension | Updated; local automated coverage | Durable transition event identity. Account lifecycle notices allow Pending/Rejected/Suspended recipients. Reactivation says activated, not newly approved. Existing approval-related email-verification policy remains intact. |
| Invitations | Managed account password setup | Existing synchronous broker flow retained | Failure no longer turns committed account creation into an error. Sanitized diagnostic supports manual password-reset resend. No applicant-confirmation duplication. |
| Authentication | Password reset/recovery | Existing synchronous role-aware flow retained | Shared branding, generic request responses, signed panel-specific URLs, broker throttle/expiry/single-use and security regression coverage retained. Reset URLs/tokens never enter the workflow ledger. |
| Email verification | Existing resend/email-change verification | Existing policy retained; handling improved | Shared branding. Sanitized failure handling. No additional registration-verification requirement. Verification links remain the existing signed links. |
| Complaints | New complaint operational alert, owner status update | Updated; local automated coverage | Staff permission/settings checks at delivery. Owner and live ticket association rechecked. No new assignment email. Original complaint permissions remain unchanged, including restrictions on management-role submission. |
| Service requests | New request operational alert, existing submission/status notices | Updated; local automated coverage | Durable delivery, live recipient/ownership/toggle checks and existing status rules. No new assignment trigger. Approval-only certificate previews remain enforced. |
| Certificates | Certificate created/issued | Updated; local automated coverage | One certificate-event delivery identity. Recheck Issued status and recipient ownership. Existing expiring authenticated download URLs remain unchanged. |
| Payments | Payment-proof approval/rejection to payer and liable owner | Updated; local automated coverage | Unique recipients, durable review event recorded inside review transaction, current payment/recipient eligibility rechecked. No new payment notification event introduced. |
| Dues | First overdue transition and delinquency transition | Updated; local automated coverage | State change and mail ledger share a transaction. Current overdue obligation/recipient and delinquent status checked before delivery. Link uses the registered homeowner dues route. No advance due-date reminder exists. |
| Announcements | Explicit Admin blast and failed-only retry | Existing tracking preserved; handling strengthened | Claim Queued delivery atomically before send. Check current publication, settings and recipient. Ambiguous outcomes become Uncertain and cannot use failed-only retry. Publishing alone still sends no blast. |
| Contact messages | New-inquiry staff alert and explicit reply | Existing tracking preserved; handling strengthened | Idempotent reply activity, atomic sending claim, branded HTML/plain-text reply and sanitized failure handling. Uncertain delivery warns against sending another reply without review. |
| Assignment / generic account edit / advance reminders | No supported email trigger found | Not implemented / not required by existing workflow | No speculative new emails added. Existing email-change verification remains covered. |

Direct send inventory: `NewHomeownerRegistration`, `RegistrationReceived`, `HomeownerAccountStatusChanged`, `WorkflowStatusChanged`, `OperationalAlert`, `CertificateIssued`, `PaymentReviewCompleted`, `OverdueDuesNotice`, `HomeownerDelinquencyNotice`, `AccountPasswordReset`, `AnnouncementPublished`, framework/panel verification/reset notifications, and `ContactMessageReplyMail`. Producers include registration, account events, workflow observer, managed request submission, payment review, dues commands, announcement actions and contact reply actions. Plain payment creation does not introduce an additional new notification.

## Delivery tracking and recovery

`email_deliveries` stores a unique logical-event/recipient/channel identity. Payloads use Laravel authenticated encryption at rest and are hidden from model serialization. The queue job contains only the delivery ID. This ledger excludes reset/verification tokens and reuses rather than replaces existing announcement/contact tracking.

States: Queued, Sending, Accepted, Failed, Uncertain, Skipped. Accepted means the configured mailer accepted submission, not inbox receipt. Fake/array/log transports do not establish external delivery. Sending has an atomic claim, so two workers cannot normally send the same row. SMTP cannot guarantee exactly once after a timeout or process crash.

Definitive SMTP rejection becomes Failed. Other send failures and interrupted Sending rows become Uncertain, with safe failure codes rather than exception messages. An operator must investigate ambiguous outcomes. `hoa:recover-workflow-mail` dispatches Queued workflow rows and flags stale Sending rows after 15 minutes across the three tracking stores. The scheduled command runs each minute with overlap protection. Duplicate queued jobs are harmless because the delivery claim is authoritative.

To retry a reviewed definitive failure, use `php artisan hoa:recover-workflow-mail --retry-failed=DELIVERY_ID`. It accepts only Failed rows below three attempts. It never retries Accepted, Uncertain or Skipped rows. Do not bulk-reset delivery statuses. Announcement failure counts include Uncertain outcomes for visibility, but Retry failed emails selects Failed only.

Keep the normal database queue worker and scheduler cron active. Ensure the worker timeout and queue `retry_after` exceed the mail job timeout (60 seconds) and transport timeout (default 30 seconds). Scheduler recovery cannot replace the worker. Inspect tracking IDs/status/failure categories and failed jobs without dumping payloads, addresses, signed URLs, private bodies or credentials.

## Branding and links

Laravel Markdown notifications, verification/reset mail and contact replies share the official `public/images/HOA.png` header, HOA colors, responsive table layout and office footer. Absolute HTTPS logo/website URLs include image-blocked text fallback. Contact replies now provide Markdown-generated HTML and plain text. Dynamic Blade content remains escaped. No private font or certificate file is exposed as a public asset.

The audit found that the homeowner dues route is `/homeowner/dues/dues-obligations`; notice links now resolve through the resource instead of guessing the URL. Existing certificate/reset/verification signed destinations are not rewritten while styling.

## Companion UI changes and data compatibility

- Complaint Others keeps the existing category and adds `category_other`. New submissions require 1–160 trimmed characters. Other categories clear the value. Staff cannot edit the resident's original specification. Historical missing specifications remain readable.
- Street is removed from current forms/display/search. Existing columns and stored values remain. New addresses store null, and stale updates cannot clear historical values. The original identity formatter remains available for historical snapshot fallback; current presentation uses `CurrentPropertyAddress`.
- Phase choices are A, B, Expansion, stored as A, B, EXPANSION. The fixed subdivision remains Southville Phase I. Existing nonstandard phases survive unrelated edits and require the established confirmation workflow before changing identity. No household, dues or landlord regrouping/backfill runs.
- Confirmed landlord matches no longer require a matching Street spelling. Unconfirmed/conflicting properties still require review. Existing legacy linked tenants remain intact.
- Users uses native deferred Column Manager, fixed Name/Actions, all requested optional columns, per-admin database preferences, Reset to Default and combined Role/Account Status/search filters. Preferences accept only server-defined columns.

## Verification record

| Check | Observed local result |
|---|---|
| Consolidated workflow, account, address, complaint, request, payment, certificate, report, contact, landlord and RBAC regression selection | 340 tests, 2,492 assertions, zero failures/errors; two skipped checks |
| Final focused UI/email safeguards, after the final source changes | 20 passed, 88 assertions |
| Skipped regression checks | Cohabitation issuance/completion and certificate-resource approval rendering require licensed Arial in the isolated configuration; not counted as passed |
| Browser UI checks, Chromium, disposable SQLite database | 4 passed: actual column checkbox/Apply/reset, persistence in a second login context, mobile keyboard toggling, Others clearing, and approved registration phase options/no Street |
| JavaScript tooling | 10 passed |
| Production Vite build | Passed; existing hero-image resolution warning remains, and the referenced public image exists |
| Formatting/source whitespace | Scoped Pint checks and `git diff --check` passed |
| SMTP authentication/transport submission, real inbox/spam receipt, Gmail/Outlook appearance | Not tested; no designated mailbox or approved external test session yet |
| Hostinger deployment, MySQL migration rehearsal, production worker/scheduler | Not performed in this local implementation |

Earlier tests expecting removed Street requirements were revised to assert the new confirmed-property contract, while retaining tampered/expired/single-use token and unrelated-property protections. The registration rollback test injects a persistence failure rather than relying on Street NOT NULL. A receipt fixture was corrected to write explicitly to private local storage, matching the download implementation; its authorization assertions remain intact. New registration payloads serialize the applicant name rather than a User model, and payment notices exclude loaded account relations.

The final focused safeguards additionally exercise null Street propagation into a newly synchronized household and recoverable registration mail after queue dispatch fails. All 20 focused tests passed after the final source changes. No tests use the application database or send external email.

Still required before any inbox claim: obtain an approved mailbox and isolated accounts, exercise each supported event with allowlisted recipients, check inbox/spam and provider evidence where available, verify sender alignment and secure links, and inspect Gmail/Outlook rendering. Never run bulk tests on real homeowners or issue official test certificates.

## Deployment prerequisites

The additive migration adds complaint specification, makes Street nullable without deleting values, and creates administrator preferences and workflow delivery tracking. Its down method intentionally does not destroy collected data; roll back code using backups rather than dropping tables/columns. Test schema changes on a production-like MySQL copy before rollout. No production migration was run during this work.

Deploy source and complete matching Vite build only after a database/file backup and review. Apply the new migration before enabling the changed application paths, rebuild configuration/views, restart workers and verify scheduler execution. Drain older queued payloads before the change where possible. Never change `APP_KEY` without an approved encrypted-data migration because the delivery payloads use it. Preserve `.env`, `.htaccess`, storage documents, issued PDFs, snapshots and production request 5. Hostinger rollout remains a separate task.

The [source manifest](../deployments/ui-email-source-manifest.txt) identifies 88 application/migration/view/translation paths. These are complete current files, including earlier integrated work, not a standalone patch against an inspected production baseline. `.env.example` documents the optional `MAIL_TIMEOUT=30`; production `.env` was not modified. Local verification artifacts are `UiReliabilityTest`, `EmailBrandingTest`, `ui-reliability.spec.js`, and the isolated browser runner, with relevant existing regression contracts updated.

## Subsequent SSH rollout

The [9 November deployment record](../deployments/ui-email-2026-11-09.md) supersedes the initial local-only deployment status above. An isolated MariaDB rehearsal and source/database backups preceded installation. The new production migration succeeded and public read-only smoke checks passed. A compatibility safeguard for older queued account-status events passed 56 local account/UI/branding tests (410 assertions).

At the user's explicit direction, all 64 queued jobs and six failed jobs were left untouched, with no worker or scheduler enabled. This release does not establish actual email delivery. Designated test accounts/mailboxes and authorized backlog processing are still required for that verification.
