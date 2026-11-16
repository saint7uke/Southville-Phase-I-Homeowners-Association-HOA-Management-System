# TextBee SMS integration audit

## Local revision — 12 November 2026

Registration SMS enrollment and guest OTP endpoints are removed/denied in the prepared interface release. Authenticated profile enrollment, verification, consent, opt-out, eligibility and provider controls remain. New applicants start unenrolled, so initial approval SMS is unavailable to those applicants; existing registration/approval email remains. Later profile enrollment applies to future events and does not replay an initial approval. Existing consenting/verified preferences remain intact. This revision is **local only**, pending separate SSH deployment; it does not describe a production configuration change. See [implementation and verification](../deployments/interface-revisions-2026-11-12.md).

Date: 2026-11-09. Scope: local implementation and isolated tests; subsequent disabled production deployment is recorded separately.

## Deployment and delivery evidence

- The local implementation phase performed no deployment or live sends. The subsequent authorized SSH deployment applied the SMS migration with sending disabled; see [deployment evidence](../deployments/textbee-sms-2026-11-09.md).
- Deployment checks initially confirmed SMS disabled, test mode enabled, an empty allowlist and empty SMS tracking tables. A subsequent authorized controlled test enabled one allowlisted number; one SMS was accepted/dispatched and the user confirmed actual receipt. After explicit live-activation approval, verified SMS-only maintenance/worker cron execution and enabled all six workflows with test mode off. Existing 64 default-queue jobs and six failed jobs remained unchanged. See the deployment report's phase-by-phase evidence.
- The exposed provider key was not used or copied into source/configuration. Revoke it and privately configure a replacement before provider testing.
- Default configuration is sending **disabled**, test mode **enabled**, and an **empty** recipient allowlist. The database master switch also defaults off. Either kill switch prevents sending.
- Automated HTTP fakes prove request construction and local state transitions, not provider acceptance, carrier delivery or test-phone receipt.
- Controlled-test evidence now includes successful device authentication, an enabled device with reported in-service SIM, API acceptance/dispatch and **user-confirmed actual test-phone receipt**. Heartbeat metadata was stale; carrier-reported Delivered status, applicable limits and end-to-end receipt for the six resident workflows remain unverified.
- Existing production queued/failed work, household assignments, certificates, finances, email delivery paths, private fonts and the user-owned `.htaccess` are outside this implementation. No historical SMS events were backfilled.

## SMS input and UI follow-up — 2026-11-09

Implementation and testing were local, followed by a separately requested [SSH deployment](../deployments/sms-input-ui-2026-11-09.md). No production configuration, SMS sends, enrollment, historical events or default/failed queue records were changed by this follow-up.

### Findings and changes

- Registration loaded the landing stylesheet, while the shared SMS component depended on Tailwind layout utilities. Added an explicitly scoped stylesheet imported by the shared SMS entry point, separating stacked consent choices, destination/Edit link, six-digit code and action buttons. Both public and Filament interfaces load the same styling.
- New primary-phone input is exactly 11 ASCII digits beginning with `09`, across public registration, profile editing and Admin test SMS. Typing/paste filtering rejects invalid edits rather than stripping characters; server validation checks the unmodified input, including whitespace, arrays and international formats. Laravel trimming excludes the relevant fields, including nested Livewire input.
- Internal E.164 normalization, fingerprints and legacy storage remain compatible. Valid legacy contacts display locally; equivalent local submissions do not rewrite a historical international contact during an unrelated profile save. Profile verification still uses the saved primary contact, never an unsaved or submitted alternative.
- Verification codes remain six ASCII digits. The UI guards pending requests, disables premature verification, displays a resend countdown, discards changed-number responses and invalidates stale local proof. HTTP 200 or a message alone no longer establishes success: request responses require a valid challenge and 202, confirmation requires `verified: true`, and preference save requires `saved: true`.
- Validation focuses the relevant phone/code control. Errors remain inline; enrollment remains optional with separate unchecked registration consent. New guidance is translated in English and Filipino.
- Rejected array-valued old input is rendered safely rather than causing a Blade error. Partial OTP entry does not participate in application-submit constraints: code length is checked by the explicit Verify action and backend, so abandoning optional enrollment cannot block an otherwise valid application. Rejected numeric edits do not leave stale custom validity on the unchanged input.

### Functionality audit

Re-inspected the six event producers, observer, transaction/after-commit dispatch, eligibility checks, reminder generation, announcement confirmation, delivery worker, HTTP adapter, budget locks, synchronization and pruning. Existing workflow templates and event identities are unchanged. Automated regressions cover initial approval versus reactivation, both payment paths, true complaint transitions, persisted due dates, monthly/weekly scheduling, shared numbers, consent/suppression, stale recipients/events, quiet hours, budgets, definitive versus uncertain failures, incoming pagination and retention. No new provider API or business operation was introduced.

### Verification evidence

- Expanded registration, HTTP enrollment, address, account lifecycle, portal, payment, email and UI regressions passed: 119 tests / 942 assertions. The invalid-input matrix bypasses only endpoint throttling in that test; the existing registration rate-limit regression passes unchanged.
- SMS integration, transaction boundaries, migration preservation and profile regressions passed: 78 tests / 371 assertions.
- Complaint workflows and role/permission regressions passed: 18 tests / 114 assertions. These runs covered 215 tests / 1,427 assertions; the final HTTP rerun adds safe old-array redisplay and translated-guidance assertions (13 tests / 192 assertions), bringing coverage to 216 distinct PHP tests.
- JavaScript tooling passed: 14 tests. Targeted Pint and `git diff --check` passed.
- Production Vite build passed (130 modules); pre-existing unresolved community hero-image warning remains.
- Initial browser run was blocked by a dashboard lazy-chart PHP timeout on the single-process Windows fixture server. The SMS test login now bypasses only dashboard navigation and consumes normal authentication feedback on a real SMS/profile/request page. This is a test-harness adjustment, not a dashboard fix or production routing change.
- All six Chromium cases passed in a serial run: real test-action confirmation/cancellation, Important announcement confirmation, homeowner OTP/preferences/opt-out, Staff denial and optional mobile registration, strict responsive phone/OTP controls, and stale/malformed/network/validation responses. Mobile and desktop component screenshots were visually inspected. Both registration cases passed again against the final rebuilt assets, including the partial optional-OTP safeguard.
- An intermediate parallel browser invocation collided in Playwright's shared artifact directory; subsequent browser verification is serial. No new handset receipt or provider delivery claim is made by these fake-gateway tests. The earlier separately authorized test-phone receipt is historical evidence only. MariaDB concurrency checks from the initial implementation were not rerun for this input/UI follow-up.

### Deployment prerequisites

No migration or environment change is required. Deploy the reviewed PHP/Blade/translation changes together with the complete matching `public/build` output; the new frontend completion flags require the matching controller version. Keep rollout separate, preserve production secrets, SMS-only cron commands, private assets and existing queued/failed jobs. Do not send a test SMS without a designated consenting recipient and separate authorization.

## Existing workflow matrix

| Module / operation | Completion point / recipient | Local behavior | Provider / phone evidence |
| --- | --- | --- | --- |
| Initial registration approval | `ChangeAccountStatus`, homeowner Pending → Active | One logical event; only verified, consenting current primary number; reactivation produces no approval event | Unverified |
| Monthly dues reminder | Persisted Monthly obligation due date minus configured days, default three | Outstanding balance rechecked; consolidated by phone/type/Manila date; no replay of missed dates | Unverified |
| Overdue reminder | Day after persisted due date, then every seven days by default | Paid/cancelled/settled obligations excluded; penalties and partial payments respected | Unverified |
| Direct payment | Successful `RecordPayment`, Recorded result | Payer and liable homeowner; one event per payment; shared numbers deduplicated | Unverified |
| Payment-proof approval | Successful `ReviewPaymentProof::approve` | Pending/rejected proofs and repeated saves send nothing; same event identity as direct payment | Unverified |
| Complaint status | Actual committed status transition | Original submitter; distinct transition UUID invalidates stale updates, including transitions while SMS is disabled | Unverified |
| Important announcement | Explicit authorized review/confirmation after publication | Important flag alone sends nothing; freeze announcement version and recipient review; reserve whole blast or reject it; recheck before sending | Unverified |
| OTP verification | Optional enrollment request | Session/user-bound code; never an administrator-readable code; no consent implied by privacy acceptance | Unverified |
| Administrator test | Active administrator with `manage_settings`, confirmed fixed-content action | One/minute and five/administrator/day; API acceptance title never says delivered | One production test accepted/dispatched; user confirmed receipt on allowlisted phone ending 5033 |
| Incoming inbox | Explicitly enabled dedicated gateway only | Read-only, device-scoped sync; exact STOP/UNSUBSCRIBE/CANCEL suppresses number without an automatic reply | Unverified |
| Service requests / certificate issuance / assignment | No approved SMS trigger in this six-template scope | Existing email/business behavior retained; no additional SMS introduced | Not applicable |

## Implementation and files

### Infrastructure

- `config/textbee.php`, documented private variables in `.env.example`.
- Additive migration `database/migrations/2026_11_09_000002_add_textbee_sms.php`.
- `app/Models/SmsBatch.php`, `SmsDelivery.php`, `SmsVerification.php`, `SmsContactPreference.php`.
- `app/Support/PhilippineMobile.php`, `SmsText.php`.
- `app/Services/Sms/`: `TextBeeClient`, `SmsGatewayException`, `WorkflowSms`, `SmsEvents`, `SmsEligibility`, `SmsBudget`, `SmsEnrollment`, `SmsReminders`, `SmsSynchronizer`, `SmsManagement`.
- `app/Jobs/DeliverSmsBatch.php`; `app/Console/Commands/SmsTick.php`.
- `app/Observers/SmsWorkflowObserver.php`; registration in `AppServiceProvider`. User audit redaction also excludes the primary contact number from new audit entries.

### Business hooks and forms

- `ChangeAccountStatus`, `RecordPayment`, `ReviewPaymentProof`, `RegisterHomeowner` integrate durable intent/enrollment at their existing transaction boundaries.
- `SystemSetting`, `Announcement` expose independent SMS controls and the protected Important flag.
- `app/Actions/Announcements/SendImportantSms.php`; announcement form/table and `SendImportantSmsAction` provide the separate confirmation workflow.
- `app/Filament/Pages/SmsNotifications.php` and its Blade view provide administration, settings, masked paginated history, safe failure categories and read-only device health. Staff are not granted this page.
- `SmsVerificationController` and three POST web routes use session/CSRF protection, throttling and explicit registration/profile scope. Profile verification always uses the authenticated user's stored primary number.
- `resources/views/components/sms-enrollment.blade.php`, `resources/js/sms-enrollment.js`, `sms-policy.js` provide optional consent and verification in public registration, portal profile and the homeowner panel.
- Registration/profile validation accepts 09…, +639… and 639… primary-number inputs. Existing saved contacts are not bulk rewritten. Emergency/landlord numbers are not SMS recipients.
- Shared app/panel JavaScript entry points load enrollment once. Scoped styles reuse HOA colors; English and Filipino keys are in `lang/en.json` and `lang/fil.json`.

### Test artifacts

- `tests/Feature/SmsIntegrationTest.php`, `SmsEnrollmentHttpTest.php`, `SmsCommitBoundaryTest.php`, `SmsMigrationTest.php`, `MySqlSmsConcurrencyTest.php`.
- `tests/Tooling/sms.test.mjs`.
- `tests/Browser/sms.spec.js`, `tests/Support/SmsBrowserSeeder.php`, `tests/Support/sms-browser-server.php`, `scripts/run-sms-browser-checks.mjs`.

The browser router is test-only, guarded by a disposable SQLite path and testing environment. All provider HTTP requests are faked. It is not a route or production integration mechanism and should not be included in a production source bundle.

## Security, reliability and cost controls

### Consent and verification

- Existing users start unverified and unenrolled. Transactional notifications and Important announcements have separate unchecked choices.
- Codes expire in five minutes, permit at most five verification attempts, and have a 60-second resend interval, five requests/number/hour and ten/IP/hour.
- Verification proofs expire in 30 minutes and are consumed in the account/enrollment transaction. Another session/user/number, an expired proof or a consumed proof cannot enable consent.
- Changing the normalized primary number invalidates verification and both opt-ins. Equivalent formats retain the same identity; no emergency number fallback exists.
- Opt-out can be saved while sending is disabled. Re-enrollment after phone-level suppression requires a fresh OTP plus explicit consent. Ordinary profile payloads cannot manufacture verification.

### Durable delivery

- Workflow intent is written within the successful business transaction; dedicated queue dispatch occurs after commit. Queue dispatch failure leaves recoverable intent and never replays the business mutation.
- Unique logical-event/phone identity prevents duplicate requests; associated user IDs are retained for shared-number recipients.
- Bodies and phone snapshots are encrypted; lookup/dedup uses keyed fingerprints. Never rotate `APP_KEY` casually: it protects both encryption and stable fingerprints. Provider-key rotation is separate.
- Provider calls use five-second connect/20-second response timeouts and no blanket POST retry. Definitively unaccepted throttling can retry up to three attempts with backoff. Authentication/configuration refusal requires correction.
- Timeouts, malformed acceptance responses and interrupted Sending jobs become Uncertain and retain budget. Unknown outcomes are not automatically resent. Known batch IDs are reconciled read-only; unknown IDs require review, not matching by message text.
- Local Accepted means only validated API acceptance. Pending/Dispatched/Sent/Delivered/Failed/Unknown are separately provider-reported states. Delivered does not become Pending because of a stale response.
- Logs/audits use safe identifiers and failure categories, not raw responses, message text, recipient addresses, codes or credentials.

### Limits and maintenance

- Atomic Manila-day segment reservations default to 50/day and include OTP/tests. Ordinary notification reservations additionally enforce three/phone/day. Definitive non-submission releases reservations; accepted/uncertain submissions retain them.
- Ordinary notifications submit only 09:00–18:00 Manila. OTP and confirmed tests are immediate. Workflow TTL is 24 hours; reminders expire at the end of their scheduled day.
- Whole-announcement reservation is transactional. Recipient/publication/content changes after review require a new confirmation; changes after queueing skip the stale version.
- `hoa:sms-tick` performs SMS-only reminders, synchronization, stale-submission recovery, local expiry and pruning. `--dry-run` reports reminder candidate counts without provider requests or writes.
- Incoming synchronization defaults off. When enabled, normal minute polling uses device-scoped cursors, with a daily two-day overlap for late uploads. Workers pause when successful opt-out synchronization is stale. Incoming messages never approve requests/payments or become official submissions.
- After 90 days, local bodies, phone snapshots, context and recipient associations are removed; event/fingerprint/provider IDs and outcomes remain for deduplication. OTP bodies are cleared on acceptance/verification or expiry, not retained for 90 days.

## Verification results

Local results are recorded separately from live delivery evidence:

| Check | Result |
| --- | --- |
| Existing account, portal/profile/address, household-head, payment, email and UI regressions | Passed: 206 tests, 1,193 assertions |
| Certificate workflow, request-preview approval, complaint permissions/reopening, reports, historical snapshots and user-role regressions | Passed: 62 tests, 427 assertions |
| Two-process MariaDB daily-budget and duplicate-batch-claim races | Passed: 2 tests, 15 assertions; separate temporary server/schema, fake provider only |
| SMS feature, HTTP enrollment, commit-boundary and additive-migration preservation | Passed: 59 tests, 216 assertions, isolated SQLite; preserved synthetic legacy records and waiting jobs across the additive migration |
| JavaScript tooling | Passed: 12 tests, including normalization and consumed-proof cleanup |
| Actual Chromium test/cancel/validation, Important announcement confirmation, homeowner OTP/consent/opt-out, Staff access and mobile registration | Passed: all four cases, three in the full run plus the corrected registration-path case in a targeted rerun; synthetic provider only |
| Production Vite build | Passed; existing unresolved hero-image warning remains unrelated to SMS |
| Formatting and whitespace | Passed: `pint --dirty --test` and `git diff --check` |
| Real API acceptance / carrier status / actual test-phone receipt | One authorized fixed test accepted/dispatched; user confirmed actual receipt. Provider Delivered timestamp not yet established; other workflow delivery checks remain unverified |
| Subsequent authorized Hostinger deployment | Installed 79 checksum-verified files, applied only the SMS migration, rebuilt caches and returned site to live mode; existing business rows and 64 queued/six failed jobs preserved |
| Subsequent authorized live activation | SMS-only cron processing verified with a no-send probe and recent maintenance timestamps; live mode and all six workflow switches enabled, incoming synchronization off, 50-segment cap retained. No residents automatically enrolled; original default/failed jobs preserved |

Browser testing found and fixed a self-referential count-placeholder render loop in the new announcement confirmation. The count display now uses distinct fields from its hidden review data. Validation and cancellation must show no success and create no blast.

The initial browser harness also needed to block external resources, avoid bootstrapping Laravel for static assets, and navigate to the existing `/homeowner/register` route (not nonexistent `/register`). These were test-harness corrections, not changes to production routes or access checks. The disposable MariaDB server was shut down after testing. Aggregate original PHP evidence above is 329 tests / 1,851 assertions; automated results do not establish live transport or receipt. Subsequent controlled-test receipt evidence is recorded separately above.

## Separate deployment / activation checklist

1. Revoke the exposed TextBee key. Configure its replacement only in private environment settings; obtain a designated consenting test number. Do not paste secrets into source or deployment reports.
2. Back up the database and affected source/assets. Confirm prior prerequisite migrations and review this additive migration. It creates SMS-only tables, nullable complaint transition-version metadata, independent settings and a false Important flag; it does not rewrite existing contacts or queue records.
3. Deploy reviewed application files and the **complete matching** `public/build` directory/manifest, excluding test fixtures and proprietary/private files. Preserve production `.env`, `APP_KEY`, `.htaccess` and unrelated application changes.
4. Apply only reviewed migrations, refresh Laravel configuration/views and verify effective SMS defaults without printing credentials. Keep the environment/database switches off and test allowlist empty until controlled testing is approved.
5. Read device health and check enabled gateway, recent heartbeat, Android SMS permissions, SIM readiness, balance and provider/carrier limits. Do not infer readiness from a stored device ID.
6. With separate approval, enable test-only sending to the allowlisted consenting phone, exercise fixed test and OTP sends, and record API evidence and actual handset receipt independently. Test recipient/client controls using synthetic accounts only.
7. Separately approve production activation and SMS-only processing. Dedicated workers, if approved, must consume **only** `sms-otp` and `sms` on the configured database connection, with `--tries=1 --timeout=60`. Verify `retry_after` exceeds worker timeout (default database value is 90 seconds); never attach the default/email queue.
8. Schedule only `hoa:sms-tick` once/minute if approved, using the host's verified PHP path. Do not start the existing global scheduler or process queued/failed production email work. Keep workers supervised; do not replay old reminder dates.
9. Document provider acceptance, provider-reported delivery, observed receipt and unavailable checks separately. Disable the environment switch immediately if recipient, consent, cap or uncertain-outcome checks fail.

No real homeowner test blasts, production request changes or official document regeneration are part of this rollout.
