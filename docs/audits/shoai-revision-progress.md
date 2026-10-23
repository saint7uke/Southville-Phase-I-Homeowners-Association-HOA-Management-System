# SHOAI phased revision — 2026-10-20

## Export-only association letterhead — 2026-10-23

Implemented the original Cohabitation document's logo/three-line letterhead only for admin Reports PDF/XLSX (including new queued admin XLSX) and dedicated admin Household PDF/new XLSX downloads. Staff/homeowner/shared downloads, CSV, previews, legacy payment/audit exports, certificates, receipts and saved exports remain unchanged. The original DOCX is unchanged; native Word extraction and pixel comparison are documented. [Affected modules, evidence and coverage limits](export-only-cohabitation-letterhead.md). No migration, live-record change or source approval.

Verification: **70 related checks / 536 assertions** passed, followed by **120 export/original-document checks / 706 assertions** after the metadata-height refinement (overlapping selections, not 190 unique tests). Native-crop decoded pixels match; synthetic three-page household/report PDFs retain 85/65 rows without overlap. Pint, 14-file PHP syntax, dictionary parity and production build pass. No new full-native, browser, Excel-native or physical-print acceptance is claimed.

## SC-07 property-based tenant payments — 2026-10-23

Implemented the user's approved rule: an eligible verified Tenant linked to an Owner or Co-owner at the same confirmed property may pay, while the existing Owner remains liable. One current-state query drives payer selection, validation and permitted dues reading; transactional recording rechecks the property and landlord/account status. Cash-only recording, one assessment per property, actual-payer history and private receipt access are preserved. Unresolved legacy identities gain no indirect access. [Changed files, actual verification and remaining release boundaries](sc07-tenant-property-payments.md) include the pre-existing rollback limitation. No new migration or live-record repair.

Final verification: **561 SQLite passes / 13 native-only skips / 6,284 assertions**; focused **native MySQL 8.4.9: 74 passes / 368 assertions**, including actual concurrent link/status revocation and payment allocation. Pint, scoped PHP syntax, translation parity and production build passed. Initial test-harness failures remain disclosed; no new authenticated browser or physical-print acceptance is claimed. Earlier dated SC-07 pending-decision notes below are superseded by this increment; independent SEC-01 recovery remains blocked.

## System-audit remediation — 2026-10-22

Implemented the authorized `c21509a` follow-up: locked complaint updates (including direct stale Eloquent saves), fresh schedule-locked assessments, bounded serialized closure, atomic role/audit writes with rollback-safe permission caching, module-aware queued operational alerts and resolved-property integrity diagnostics. Targeted npm patches address the reported development advisories; UI dictionaries and static-fixture preflight/CI coverage are updated. [Per-finding evidence and remaining gates](sc-remediation-20261022.md) distinguish actual tests and initial failures from pending acceptance. No migration or live-record repair; SC-07 and independent SEC-01 recovery remain unresolved, with no automatic approval or push.

Final evidence: **523 SQLite passes / 10 native-only skips / 6,053 assertions**; focused **native MySQL 8.4.9: 57 passes / 233 assertions**; combined **15 browser passes** across Chromium/Firefox/WebKit without retries. Pint, 514-file PHP syntax, Node tooling/syntax, Composer checks, production build and npm audits pass. Existing MariaDB integrity inspection was read-only; household review/queue counts are unchanged. Native results are focused, not a final full-native acceptance or host-trust sign-off.

## Global English/Filipino switcher — 2026-10-22

Implemented the selected compact native dropdown with a hidden accessible label, 14px text,
44px target, shared theme-aware CSS, icons, stable busy feedback and request deduplication.
All three panels place it before the avatar; public navigation, authentication/recovery/status
screens and the legacy portal use the same styling and language handler. Mobile placement and
intentionally light cards are respected. Existing locale endpoint/preferences, translations,
permissions and unsaved-state/no-reload behavior are retained; no migration or new dependency.

Focused backend regression: **99 passed / 3,009 assertions**; Pint, syntax and scoped diff checks
passed. Build passed in **16.88 seconds**, with the existing unrelated hero-image warning.
Tool policy blocked isolated application-server setup before execution. A narrower server-free
rendered-component browser suite is used instead; it does **not** verify real login, persistence,
temporary uploads or Livewire modal state. The eight guarded full-workflow browser cases remain
available but unexecuted for this increment. Component checks passed in **Chromium 4/4,
Firefox 4/4 and WebKit 4/4**, including responsive/theme/contrast and request-state checks;
the initial Firefox subpixel-measurement failure and corrected rerun are recorded explicitly.
See [implementation and evidence boundaries](localization-coverage.md).
No live user preferences, source approvals, financial records, deployment or historical PDFs changed.

## Hide confirmed Cohabitation setup text during creation (2026-10-22)

The daily certificate form no longer displays the Confirmed success banner once its current
source is approved. Warnings for missing/incomplete or unconfirmed sources remain visible.
Only the existing field's visibility changed; backend approval, role permissions and issuance
safeguards remain intact. No migration, source approval write or historical PDF change occurred.
Admin/staff hidden-state and actionable-warning regression coverage was added; two-file Pint,
syntax and scoped diff checks passed. Focused SQLite: **36 passed / 299 assertions**;
no full-suite or browser verification is claimed for this change. See
[increment evidence](original-cohabitation-certificate.md).

## Cohabitation source confirmation recorded on explicit request (2026-10-22)

The user confirmed the corrected source/printed signatory and explicitly designated
`admin@southville.test`. After verifying that existing account's active administrator status
and settings permission, the existing source-confirmation service recorded **Confirmed** for
**JOSEPHINE D. ALARZAR / SHOAi PRESIDENT**, source/template **ID 4, version 1**, at
**2026-10-22 08:59:31 Asia/Manila**. Audit **ID 123**, actor **ID 1**, `panel=system` records
the delegated CLI operation accurately; no interactive login or independent HOA resolution
is claimed. A private pre-change backup and final read-only verification confirm the source
checksum, approval attribution, unchanged source map and preservation of other source versions.

This supersedes the pending local Cohabitation source-confirmation status below, not other
human/security release gates or approvals on other databases. No source bytes, account roles,
financial records or historical PDFs were changed; no certificate was issued, migration run,
notification sent, push or deployment performed. No app behavior was modified or full suite
rerun. See [confirmation evidence and scope](original-cohabitation-certificate.md).

## Cohabitation signatory punctuation (2026-10-21)

The user authorized correcting `JOSEPHINE D, ALARZAR` to **JOSEPHINE D. ALARZAR** in
the separate source copy. Native Word export and the current map now match the corrected
name. Original upload, previous source assets, existing PDFs and historical snapshots are
preserved. Pixel differences from the preceding PDF are confined to the signatory line;
all other extracted text, fonts, Letter dimensions and blank coordinates are unchanged.
Copy-only source reproduction and PDF checks cover both authorized corrections.

Focused SQLite: **34 passed / 270 assertions**; related certificate/receipt regressions:
**102 passed / 595 assertions** (136 targeted tests, not the full suite). Three-file Pint, syntax and file-only PDF
preservation checks passed. Vite build passed in **20.61 seconds** with the existing hero-image
warning. The new regression verifies previous approval/PDF immutability,
stale-preview rejection and required fresh confirmation. A browser-fixture startup command
was blocked by tool policy before execution; no new browser pass is claimed. No full or
native suite is claimed for this punctuation-only increment. See
[source provenance and exact evidence](original-cohabitation-certificate.md).

**Official approval remains pending:** the existing approving administrator account has not
been identified. No live source/signatory approval, migration or deployment was performed.

## Authorized Cohabitation phase correction (2026-10-21)

The user authorized the narrow original-document edit and chose registered-phase autofill.
A separate corrected DOCX and faithful native Word PDF now resolve the missing-source blocker;
the upload is unchanged. All 19 other DOCX parts match, and PDF pixels outside the phase-line
region are identical. Both phase locations use the registered suffix; the obsolete Additional
address detail field/rule is removed. The source is ready for one-time review, **not automatically
approved**. No new upload, migration, live approval or historical regeneration was performed.
See [provenance, verification and remaining gates](original-cohabitation-certificate.md).

Verification: expanded focused **103 passed / 737 assertions**; full SQLite **458 passed /
4,776 assertions / six native-only skips**; native MySQL **8.4.9: 9 passed / 78 assertions**,
including concurrent issuance. Final Chromium **2 passed** (2.1 minutes), with responsive,
keyboard, explicit light/dark Axe, private preview and matching saved print/download bytes.
The initial browser timeouts and fresh-fixture rerun are documented. Original-PDF preservation,
blank-region, Arial/baseline, Unicode, watermark and non-interactive output checks passed;
11-file Pint, syntax, diff checks and build passed. Owned test listeners were stopped.
No real approval or deployment is implied by isolated test-only confirmation.

## Cohabitation setup simplification (2026-10-21)

Replaced the separate Cohabitation Review/Confirm buttons with one status-aware action inside
Document setup. Missing files show explicit requirements without an unusable review/confirmation
submit; ready sources combine review and one-time signatory approval. Confirmed versions hide
the setup prompt. Certificate creation shows the same status and uses **Preview → Issue certificate**
without an extra dialog. The unavailable-source URL now renders private, localized guidance with
a return link rather than a debug exception. Backend authorization, source approval, auditing,
preview binding and duplicate prevention are unchanged. The corrected original/export/mapping
remain required; no source approval, migration or historical regeneration was performed.

Verification: full SQLite **449 passed / 4,702 assertions / six native-only skips**, then final
focused SQLite **50 passed / 2,650 assertions**; native MySQL **8.4.9: 41 passed / 264 assertions**
including concurrent issuance. Build, eight-file Pint, syntax, scoped diff checks and 1,161-key
locale parity passed. Targeted Chromium setup/header checks passed after documented timing,
modal-wrapper and theme-state harness corrections; a real primary-button contrast defect was
fixed with the existing darker-blue class. Final combined Chromium: **4 passed**, 1.7 minutes,
including responsive/keyboard and explicit light/dark Axe checks, with no retries/skips in that
final run. Owned test listeners were stopped. See [increment evidence and remaining gates](original-cohabitation-certificate.md)
for coverage limits and recorded failed attempts.

## Original Cohabitation document (2026-10-21)

The existing Cohabitation type now shares the original-PDF preview/issuance pipeline, with registered/manual person details, birth dates, verified years, requester selection and per-source signatory confirmation. No replacement design or management page was added. The uploaded DOCX is preserved unchanged; activation still requires the user's corrected original with a blank phase suffix and a reviewed Word PDF/mapping. See [implementation, test evidence and remaining gates](original-cohabitation-certificate.md). No migration or live activation is included.

Final verification: **445 SQLite tests passed / 4,648 assertions / six native-only skips**; native MySQL **8.4.9** focused/concurrency **32 passed / 274 assertions / two SQLite-only skips**. Chromium passed the Cohabitation form/blocked-source workflow and the shared monthly-receipt generation regression after documented locator/timeout retries. Responsive, keyboard and light/dark Axe checks passed for the tested form; no real corrected-original visual/print acceptance is claimed. Pint (20 PHP files), syntax (372 files), 1,149-key locale parity, diff checks and build passed. Native JSON-key reordering and request form-mode defects were repaired. Owned test listeners were stopped. Source/HOA/host-security gates remain open.

## Shared monthly receipt across Certificates and Payments (2026-10-21)

Both modules now use one saved original PDF for the same payment. Payments links to the existing Certificates preview workflow when generation is needed; unsupported payments retain a clearly labelled detailed receipt. Homeowner/payer reading permissions are preserved without granting generation access. No new migration or original-document change is introduced. See [the increment audit](shared-monthly-receipts.md) for actual results, failed attempts and remaining coverage.

Verification: full SQLite **427 passed / 4,477 assertions / five native-only skips**, followed by **15 post-correction focused tests / 166 assertions**; native MySQL **8.4.9** focused/concurrency **42 passed / 355 assertions / three SQLite-only skips**, plus the new confirmation test **1 passed / 11 assertions**. Chromium completed the Payments-to-Certificates generation and matching-file return flow, responsive/keyboard checks and light/dark/dialog Axe. The observed button-contrast defect was fixed using the existing darker-blue token. Pint, syntax, diff checks and final production build passed. Failed browser harness attempts are documented. Owned test listeners were stopped. Other engines, physical-print approval, full native-suite acceptance and independent host-security recovery remain outside this increment.

## Homeowner-based Resibo Butaw form (2026-10-21)

The existing creation page now supports **liable homeowner → recorded payment → read-only details → preview → confirmed generation → View/Print/Download**. Original PDF blanks/layout and financial eligibility remain unchanged. No new migration, live source approval or ledger write is included. Full SQLite regression passed **413 tests / 4,298 assertions**, with five native-only skips; final receipt SQLite recheck passed **29 tests**, and the native MySQL receipt recheck passed **28 tests** with one SQLite-only skip. Browser acceptance remains **incomplete** because of local runner/PHP timeouts; no successful final browser run is claimed. Test listeners were stopped. See [the increment audit](homeowner-butaw-form.md) for failed attempts, interrupted extra runs, corrections and remaining verification.

## Original uploaded PDF and receipt-history correction (2026-10-21)

The newly supplied single-slip `resibo butaw.pdf` replaces the earlier ten-slip Word-exported source for new receipts. Its preserved SHA-256 is `ae0f3fb8ca5470aeebb97c22eec429536e0e71a7648ef0cf8f91cc389361dc30`. The private source-review route returns these exact bytes; filled previews/official copies use the newly measured original-font mapping without cropping, resizing or redesign. Earlier source records/assets/PDFs remain unchanged; their approval does not transfer to the distinct current source.

Monthly receipts is now explained as generated-PDF history. Empty history hides the unusable selector and offers a payment-authorized `?document=butaw` creation shortcut. Missing schema shows setup guidance; receipt eligibility and approved-service-request behavior remain unchanged. The interrupted certificate-list syntax error is fixed. English/Filipino copy, updated single-slip verification and compatibility tests are included. No new migration or live source approval is part of this increment. See [the source-correction audit](receipt-pdf-source-correction.md) for final results and remaining print/HOA/security gates.

Final verification: **402 passed, five native-only skips, 4,178 assertions** on full SQLite (632.14 s); **21 focused tests / 162 assertions** passed. Four distinct Chromium workflows passed across a three-pass/one-pending-dialog timing failure and successful targeted generation retry. Source review is byte-identical to the upload, and the visual checker found no changes outside the five blank rectangles (exact Calibri 11.04, Letter size, one original slip). Build passed in 16.76 s; Pint, changed-file syntax, diff checks and 1,090-key English/Filipino parity passed. This increment did not rerun native MySQL or all browser engines. The isolated browser server was stopped; live data, actual approval, physical-print and independent security gates remain unchanged.

## Certificate interface simplification (2026-10-21)

The certificate header now separates daily work from source setup: **New document**, **Monthly receipts**, and one neutral administrator-only **Document setup** dropdown. Residency/Resibo approvals hide once the current source is confirmed; Cohabitation approval appears only for an existing draft. Source-review links remain in the dropdown. Saved-receipt viewing has no submit/confirmation step. Setup dialogs explain the one-time requirement and label the submission **Confirm source**.

Domain permissions, source/signatory review, immutable approvals, original layouts, previews, issuance and private PDF access remain enforced. No source was automatically approved, and no live migration/data change occurred. Readiness checks do not create source/settings records and do not replace authoritative issuance checks. See [interface changes and verification](certificate-interface-simplification.md) for exact test results and scope.

Final verification: **394 SQLite tests passed, five native-only skips, 4,112 assertions** (668.77 s). Focused document/interface tests passed **41/239 assertions**, and a direct immediate-visibility recheck passed **3/49 assertions**. Chromium passed both admin/staff header scenarios and the updated original receipt flow with an already-approved source. Initial browser timing failures and corrected test-only null expectation are retained in diagnostics. Production build, Pint, changed-file syntax, dictionary parity (1,082 keys each) and diff checks passed. No native/cross-engine rerun is claimed for this UI-only increment. The isolated browser server was stopped; the user's normal application/database remain running. This increment requires no new migration.

## Latest increment: registration, confirmed property identity and original receipt (2026-10-21)

Implementation and rollout details: [registration/Phase/Resibo audit](registration-phase-resibo-butaw.md). This increment preserves the earlier uncommitted revisions; no live migration, real HOA approval, account provisioning, deployment or push occurred.

- Registration now has readable labels/checklist states, no House Number input, and required normalized Phase suffix. New accounts and profiles support null house numbers; historical values remain. Availability/landlord checks and final registration use the same property identity and stale-response protections.
- Confirmed identity is base phase + suffix + block + lot. Unknown legacy suffixes are not guessed. Authorized confirmation rejects relationship, household and current-assessment conflicts. Current billed liable-account moves and archived unresolved assessments require HOA review rather than silently moving or duplicating liabilities. A read-only `hoa:review-property-identities --json` report includes these cases.
- The original `resibo butaw.docx` was preserved unchanged. PowerShell COM export variants stalled; native Windows `cscript`/Word COM exported the original successfully with macros disabled/read-only access. No approximate HTML or replacement design was used. Source hashes and measured original-font mappings are recorded in the linked audit.
- Certificates now offers Monthly Dues Receipt as a payment document, not a catalog/service-request type. Accepted 2026 monthly Cash payments with no penalties and verified property/recorded collector data have private expiring previews, explicit generation, immutable snapshots, concurrency-safe reuse, and saved View/Print/Download. Only the first of ten original slips is populated; all others remain blank. Unsupported historical payments retain the detailed receipt.
- Two additive migrations (`000006` identity/nullability, `000007` payment-document provenance) were tested only on disposable databases. Deployment requires backups, clone rehearsal, reviewed legacy-identity findings and explicit approval. The receipt source and updated Residency suffix mapping still require actual authorized HOA review/confirmation; synthetic tests do not approve either.

Verification records:

- Final full SQLite: **389 passed, five MySQL-only skips, 4,031 assertions**, 409.67 seconds; `storage/logs/registration-butaw-full-sqlite-complete.txt`. Includes the archived-assessment regression and preserves the original 489.00 assessment without recalculation.
- Native MySQL 8.4.9 prior checkpoints: 90 passed/560 assertions, then 57 passed/352 assertions after later guards. The subsequent final native run was interrupted before producing a result; its empty log is not a pass. A separate retry is recorded below when complete.
- Browser combined run completed 17 passing cases (six Chromium, six Firefox, five WebKit) before the environment interrupted its last receipt case. No clean 18-case combined pass is claimed. A WebKit-only receipt rerun supplies the remaining evidence when complete. Cases cover 320/768/1440 widths, light/dark media, bounded Axe checks, locale switching with unsaved password/Phase/upload, actual registration and payment-to-original-receipt workflow.
- Latest PHP syntax check: 418 application/database/routes/tests/script files passed; config/bootstrap follow-up is recorded below. Composer validation passed, production npm audit reported zero vulnerabilities. Composer audit initially timed out contacting Packagist; its retry reported no advisories (`registration-butaw-composer-audit-retry.txt`).
- Earlier failed browser checks remain in diagnostics: inherited light text on white was corrected; obsolete test selectors/payment-confirmation expectations were fixed without weakening backend authorization/ledger assertions. Existing payment CreateRecord confirmation behavior was not redesigned in this increment.

Final browser completion: the isolated WebKit receipt-only retry **passed (1 test, 2.8 minutes)**. There are now **18 distinct passing scenarios, six per Chromium/Firefox/WebKit**, across the interrupted 17-case run and this targeted completion. No unconditional clean combined run is claimed. Final Pint, JavaScript syntax, whitespace and repeated first-slip-only visual checks passed; the original Downloads DOCX hash remains unchanged. The receipt verification measures document preservation, not physical printer calibration or general PDF accessibility certification.

Final static/build evidence: **435 PHP files passed** (418 application/database/routes/tests/scripts plus 17 config/bootstrap); production Vite build passed in **35.57 seconds** (`registration-butaw-build-complete.txt`). The existing runtime hero-image resolution warning remains. English/Filipino dictionaries have **1,076 matching keys**, verified with a case-sensitive JavaScript key comparison. Two ad-hoc parity command invocations failed due to Windows argument quoting and PowerShell's case-insensitive JSON handling; the corrected stdin-based Node check passed without changing dictionary identities. No application/runtime failure is inferred from these shell-only command errors.

Final native verification: **61 passed, 379 assertions, no skips**, 344.48 seconds, on native MySQL Community Server **8.4.9** (`storage/logs/registration-butaw-native-complete-retry.txt`). The guarded preflight confirmed loopback port 33187 and disposable `hoa_registration_test_20261020`; it did not target `hoa_system`. This selection covers final property/archived-assessment guards, populated migration compatibility, profile saves, original receipt rules, financial actions, concurrent Owner claims, payment allocation/idempotency, receipt generation and original certificate issuance. It is a focused native rerun, not a claim that the entire final suite was rerun on MySQL. The full final suite was run on SQLite as recorded above.

The audit-owned browser listener on 8019 was stopped after the WebKit retry. The standalone MySQL listener on 33187 is shut down only after checking its process command line, version and dedicated data directory. Disposable fixtures and ignored diagnostics are retained for review; no material files or financial/account records were deleted. The pre-existing XAMPP database and port-8000 application remain outside cleanup. No external notifications were sent; test notifications/mail use fakes/array transport.

Pending external gates: actual receipt/Residency visual and signatory approval, Filipino editorial review, populated-data rollout approval, and independent host/credential incident recovery. Physical printer output and every production record combination have not been verified.

## Constraints

- Preserve historical accounts, payments, issued documents, and audit history.
- No live database migrations, account provisioning, real notifications, push, or deployment.
- The existing remote-code-loader incident remains a production-release blocker; source repairs do not certify host integrity.

## Phase 1: priority repairs

- [x] Reproduce the profile callback dependency-injection defect (`$value` receives null rather than submitted `$state`).
- [x] Share phone normalization across final validation and Filament validation/dehydration; retain required fields and 09-format checks.
- [x] Reproduce and fix the additional legacy missing-phase blocker: show/validate the existing default phase consistently, but do not rewrite the stored null phase.
- [x] Add actual Livewire save/remount and failed-save preservation tests.
- [x] Cash-only new recording in the shared validator, with read-only form value.
- [x] Deny new payment proofs in request authorization and the shared action; remove their visible entry points.
- [x] Preserve review of historical proofs using explicit legacy fixtures in regression tests, not a test-only policy bypass.
- [x] Lock payment method/reference identity during metadata edits.
- [x] Remove the payment-form terminal command; improve obligation labels and empty guidance.
- [x] Add authorized, confirmed current-month recovery using the existing generator and property locks.
- [x] Verify an existing PHP 489 assessment survives a PHP 500 schedule/recovery run unchanged.
- [x] Browser save/refresh, success notification, responsive and Axe checks passed against synchronized assets.
- [x] Full SQLite checkpoint, final focused regression, formatting, syntax, production build and security audits.
- [x] Disposable MariaDB recheck: 14 passed, 92 assertions, including concurrent payments and profile saving. Native MySQL 8.4 remains unverified.

## Phase 2: implemented source increment

- [x] Canonical login with normalized credentials, role-selected guards, legacy redirects, throttling, auditing and preserved account-status/verification rules.
- [x] Homeowner-only complaint submission; administrator/staff and management multi-role submissions denied in policy, domain action and creation routes.
- [x] Two new request/issuance types; server-controlled grandfathering migration and immutable request identity preserve retired-type processing.
- [x] Structured cohabitation names/address, private watermarked drafts, explicit approval gate, versioned plain-text templates and configured signatories.
- [x] Certificate snapshots preserve version, signatory and resident/address details. Approved versions and issued identity are immutable.
- [x] Receipt layout retains one accepted payment, actual payer, liable account, collector, reference, amount, block/lot and actual billing month/year. No stored historical PDF was rewritten.
- [x] Explicit administrator invitation via expiring password-broker links and random undisclosed initial passwords. Accounts remain Pending; no officers provisioned from document names.

## Phase 3: implemented source increment

- [x] Additive household/member structures; one normalized physical property identity, conservative explicit backfill and dry-run review reporting.
- [x] Admin/staff provisional RBI management under `manage_homeowners`, administrator-only archive/restore, restricted resident summaries/PDFs.
- [x] Account-only membership moves retain ended memberships, leave other residents/property/financial identities unchanged, and flag former associations for review.
- [x] Administrator project management with additive `manage_projects`, private re-encoded images, transactional cleanup, audited publication/archive/restore, and truthful React public empty states.
- [x] Allowlisted CSRF-protected/throttled locale preference, browser/Livewire middleware, recipient locale preference, shared dictionaries/common validation, translated form/navigation labels and React locale context.
- [x] No-reload language switching preserves unsaved credentials/profile input. Responsive/keyboard/Axe checks passed in isolated Chromium; expanded upload preservation is checked separately below.
- [ ] Complete human-reviewed Filipino copy coverage for every legacy helper, custom validation message, notification body and promotional section. Untranslated strings fall back to English; stored data and historical PDFs are not translated.
- [ ] Exhaustive cross-panel browser coverage (including project/template modals, every restoration conflict, and non-Chromium engines).

## Verification evidence

- Profile save/remount test: passed (11 assertions).
- Principal Phase 1 selection: 59 passed, 272 assertions.
- Additional Cash/recovery/metadata/proof-endpoint tests: 4 passed, 19 assertions.
- Failed-profile preservation and Filament creation follow-up: 2 passed, 22 assertions.
- Production Vite build: passed; existing public hero-image resolution warning remains.
- Browser work uses the ignored SQLite audit fixture on localhost:8013 with array mail; no production users are involved.
- Initial new browser test used an exact label that excluded Filament's required marker, then asserted before the save response completed. The test now uses field wrappers and awaits the matching Livewire response. One overlapping test run also collided on shared trace artifacts; final runs must be serialized.
- The browser save test then exposed a real second defect: legacy `homeowners.phase = null` was displayed as blank in a disabled required field. The existing address/default-phase semantics are now used for display and validation. A regression confirms profile updates leave the stored null phase unchanged.
- Full SQLite suite at its final enumeration checkpoint: **301 passed, 2 skipped, 1,411 assertions** (561.66 seconds). The subsequently added legacy-phase/recovery-UI regressions are covered by the final focused selection, not claimed as part of that earlier full run.
- Composer manifest validation passed; Composer/npm security audits each reported zero known vulnerabilities.
- Published Filament assets were out of sync with installed package files and have been republished through `filament:assets`. The browser save test passed before republishing when it waited for the additional notification round trip; asset drift is not asserted to be the cause of that timing failure.
- New browser save/refresh test: **1 passed** (42.0 seconds including setup). The existing responsive/Axe validation test also passed in its prior run; final paired results follow below.
- SQL verification is **blocked**: no listener was available on localhost:3306. The disposable MariaDB selection was interrupted rather than left repeatedly waiting for a missing service. No SQL service was started and no live database was modified. The two MySQL-only tests remain skipped locally; native MySQL 8.4 acceptance remains outstanding.
- Final related-module selection after all Phase 1 PHP changes: **66 passed, 322 assertions** (82.43 seconds), including the additional legacy-phase and confirmed recovery-action tests.
- Final paired Chromium check against republished assets: **2 passed** (1.7 minutes), covering actual save/refresh/notification and the existing responsive/Axe validation workflow.
- Final `php vendor/bin/pint --test`, `git diff --check`, and syntax parsing of **366 PHP files** passed.
- The audit-owned localhost server was stopped after verification. Ignored SQLite fixtures/test artifacts remain available locally. No commit, push, deployment, live-data repair or schema migration was performed.

## Phase 1 verification commands

```text
php artisan test --compact
php artisan test --filter="HomeownerProfileTest|DuesAndPaymentManagementTest|DuesWorkflowTest|SystemAuditRegressionTest" --compact
php vendor/bin/pint --test
php composer.phar validate --no-check-publish
php composer.phar audit --format=summary
npm run build
npm audit --audit-level=moderate
```

Browser verification requires isolated seeded accounts and an isolated SQLite database, array mail, an explicit localhost `PLAYWRIGHT_BASE_URL`, and `HOA_BROWSER_HOMEOWNER_EMAIL`/`HOA_BROWSER_HOMEOWNER_PASSWORD` environment values. Run `npx playwright test tests/Browser/profile-validation.spec.js --project=chromium --workers=1` serially; do not run multiple Playwright commands against the same artifact directory. The expanded save test waits for the matching save response and asynchronous notification delivery rather than assuming immediate completion.

There are no Phase 1 migrations to apply. The main changed modules are `MyProfile`/shared profile validation; payment form/validation/proof authorization/metadata updates; the current-month dues action and its confirmation UI; published vendor assets; and regression tests. Database structures, issued PDFs, account roles, and stored payment methods/references were not rewritten.

## Verification gate / remaining work

Phase 2 and Phase 3 source changes are now implemented alongside the preserved Phase 1 repairs. Deployment remains blocked by native MySQL 8.4 acceptance, independent host/credential incident recovery, reviewed backups/migration approval, and HOA confirmation of signatory/template/RBI wording. The translation and browser coverage limitations above remain explicit work items, not a claim of complete system verification.

Counts above overlap; they must not be added as a distinct-test total. No claim is made here that uncompleted phases are implemented.

## Phase 2–3 verification and confirmed repairs — 2026-10-20

- The original disposable MariaDB database `hoa_audit_test_20261018_1640` failed with InnoDB tablespace error 1813 before assertions. It was left untouched. A separately checked, newly created disposable database `hoa_phase23_test_20261020` was used instead; never `hoa_system`.
- MariaDB is **10.4.32**, not native MySQL 8.4. Phase 1 recheck: **14 passed, 92 assertions**. Expanded Phase 1–3 selection: **32 passed, 219 assertions**, including both row-lock payment concurrency tests, Cash-only handling, legacy proof review, unchanged assessments, profile persistence, authentication, templates, household privacy and project publication.
- Initial new SQLite increment: **16 passed, 113 assertions**. Related complaint/certificate/audit/notification selection: **48 passed, one outdated legacy fixture failed**; corrected grandfathered fixtures and a subsequent focused check passed.
- A full SQLite checkpoint after policy/fixture updates passed: **321 passed, 2 skipped, 1,523 assertions** (489.69 seconds). Skips are the separately exercised SQL-driver concurrency tests. Additional regression tests added afterward are reported separately rather than included retroactively.
- Confirmed defects found while integrating: a redirect helper intercepted POST login submissions (changed to GET-only); the selected guard needed to become the request default for database-session ownership/revocation; populated SQLite circular-household rollback needed child-first handling; the Filament locale switcher needed the actual CSRF token source. Regression checks cover these corrections.
- Initial browser harness used an incorrect working directory and failed before the login form loaded. That harness was corrected. A later language-check timeout and CSRF failure were reproduced and corrected; final paired Chromium locale checks passed **2 tests**, covering retained input, persisted guest language, React project empty states, responsive profile layout and no serious/critical Axe violations.
- Build and Composer manifest validation passed; Composer and npm audits reported **zero known vulnerabilities**. The existing hero asset resolution warning remains a build warning, not a newly generated or substituted image.
- Browser fixtures use only `database/phase23-browser-test-20261020.sqlite`, intercepted/array mail and localhost:8014. No production accounts or real notifications were created.
- Subsequent targeted SQLite regression: **63 passed, 423 assertions**, including grandfathering migration round-trip, private draft ownership, explicit workflow actors, storage cleanup and profile/tenant regressions. Additional MariaDB selection: **16 passed, 120 assertions**, including setup token expiry/reuse, draft privacy, workflow metadata and project storage rollback. Counts overlap previous selections.
- Expanded Chromium language run: **2 passed** (1.8 minutes), now including preserved temporary photo uploads and unsaved React contact data under normal CSRF enforcement. Profile persistence/validation run: **2 passed** (1.4 minutes), including refresh, live conflicts, responsive layouts and Axe checks. The audit-owned localhost:8014 server was stopped afterward; the user's pre-existing localhost:8000 server was not stopped.
- Upload-harness correction: browser HTTP must not run with `APP_ENV=testing`, which activates Livewire's unit-test-only `tmp-for-tests` disk across independent HTTP requests. Runtime now uses `APP_ENV=local` with the same isolated SQLite database and array mail; fixture seeding alone uses guarded testing mode. A contact input first needed scrolling into its intentionally reveal-animated section before editing.
- Final certificate hardening rejects forged ordinary edit submissions, certificate-number changes and replacement of an already persisted PDF path. Snapshot-backed cohabitation names are displayed read-only on the detail form. Signatory-gate errors are attached to the certificate type field. A schema-level Filament callback initially used field-only `$operation` injection; it now receives `Schema` and reads its operation explicitly, and focused regressions passed.
- Syntax parsing passed for **399 PHP files** at the full scan and **113 changed PHP files** at the later scan. Formatting and whitespace checks passed at their recorded checkpoints. No dedicated PHPStan/Psalm configuration was introduced.
- Final broad SQLite delivery checkpoint: **328 passed, 2 skipped, 1,561 assertions** (301.59 seconds). The two skips are SQL-only concurrency cases already passed on disposable MariaDB. Two modal-specific tests added after suite enumeration passed in the separate **5 passed, 40 assertions** selection; a subsequent **3 passed, 27 assertions** check also verifies that private resident drafts require current membership and verified email. These counts overlap and must not be summed.
- Domain validation is mapped to the current modal/form state path and announced through safe notifications. Final modal checks cover duplicate household identity, unknown template placeholders, incomplete project publication, missing signatory approval and forged certificate edits. An exception-lifecycle prototype was discarded after recursive handling exhausted test memory; the delivered implementation uses explicit action wrappers and passed the focused checks.
- Final production build passed (15.91 seconds); the previously recorded hero resolution warning remains. Full Pint check, targeted checks after the last PHP edits and `git diff --check` passed. Composer manifest validation and both dependency audits remained clean; no dependency installation or lockfile update was needed for this increment.
- Populated grandfathering/backfill migration round-trip coverage is strongest on SQLite. MariaDB schema application, payment concurrency, workflow and household/project selections passed, but this is not exhaustive populated-database rollback coverage or native MySQL 8.4 acceptance. No actual populated production backfill was attempted.

Reproduction commands for the final increments:

```text
php artisan test --compact
php artisan test --filter="PhaseTwo|PhaseThree|LandlordTenantManagementTest|HomeownerProfileTest|WorkflowNotificationTest|AdminComplaintCreationTest" --compact
php vendor/bin/pint --test
php composer.phar validate --no-check-publish
php composer.phar audit --format=summary
npm.cmd audit --audit-level=moderate
npm.cmd run build
npx.cmd playwright test tests/Browser/phase23.spec.js --project=chromium --workers=1
npx.cmd playwright test tests/Browser/profile-validation.spec.js --project=chromium --workers=1
```

MariaDB commands additionally set `DB_CONNECTION=mysql`, `DB_DATABASE=hoa_phase23_test_20261020`, and `HOA_ALLOW_DESTRUCTIVE_TEST_DATABASE=true` for the explicitly disposable database. Never use that opt-in with `hoa_system`. Browser commands require the isolated server/environment described above, not the user's existing server or production accounts.

## Reviewed deployment sequence (not executed on live data)

1. Complete independent incident recovery and obtain reviewed database/private-file backups. Preserve existing uncommitted Phase 1 changes. Do not run the development seeders on customized production roles.
2. Review the additive migrations `2026_10_20_000001` (templates, request eligibility/actors, certificate snapshots), `000002` (households/members, projects/images, locale), and `000003` (additive project permission). Test migration/rollback against a separately named restored copy first. No migration provisions officers, approves templates or rewrites historical financial allocations/PDF files.
3. Apply migrations only after explicit deployment approval. Build frontend assets and restart approved queue workers through the existing deployment procedure. Secure setup links use the existing broker expiry/single-use tokens; account approval remains separate.
4. Run `php artisan hoa:backfill-households` first: **default is read-only**. Review IDs/reasons for incomplete addresses, ambiguous Owners and unapproved applications. Only an explicitly approved `--apply` links eligible memberships; rerunning is idempotent. It does not merge accounts or create dues/payment ledgers.
5. Confirm the authorized signatory through the administrator Certificate Templates page with an audit note. Retain the operational legacy Residency layout until its replacement version is approved. Cohabitation issuance remains blocked until an approved version exists; draft previews never allocate official numbers or issuance notifications.
6. Review provisional RBI terminology and Filipino copy with the HOA. Publish only genuine completed projects. Restored projects are unpublished until explicitly republished. Existing private images are not exposed by guessed paths/IDs.

Rollback is not a data-repair mechanism: removing the new tables discards their newly entered census/project/template data. Restore reviewed backups or roll forward for populated deployments; do not execute a destructive rollback as routine troubleshooting. Migration testing used disposable databases only.

## Localization and release-readiness follow-up — 2026-10-20

- Implemented: shared browser translation module, expanded dictionary parity/placeholder coverage, static Blade/form copy, registration/profile feedback, presentation-only option/status translation, notification placeholders, and an inventory command. See `localization-coverage.md` for exclusions and incomplete coverage; human approval is not implied.
- Confirmed browser repairs: use Filament 4 field-label selectors; restore browser-created validation messages removed by unrelated Livewire morphs without storing credentials/values. The localized contact form now retains its form reference across `await`, preventing a false failure after a successful save; it uses the existing CSRF/session-aware browser contact route so the selected locale applies.
- Latest completed full SQLite checkpoint: **337 passed, 2 SQL-only skips, 3,559 assertions** (384.52 seconds). A subsequent final-copy checkpoint and native MySQL/browser runs are recorded separately when complete.
- An earlier verification attempt failed from a concurrent Vite-manifest replacement, a random-middle-name fixture assertion and a new test leaving the default database connection changed during RefreshDatabase teardown. Those harness defects were corrected; the reporting/readiness focused rerun passed **18 tests, 1,616 assertions**. Do not sum overlapping selections.
- The localized invalid-phone/correction/language-switch Chromium regression passed twice after fixes (one recorded run: 2.7 minutes; later run: 59 seconds). Broader browser acceptance is still in progress; initial navigation timeouts and interrupted runs are not passes.
- Native MySQL **8.4.9** was discovered outside PATH at `C:/Program Files/MySQL/MySQL Server 8.4/bin`. A standalone loopback instance on port **33184**, using only `storage/framework/testing/mysql84-readiness-20261020`, passed identity preflight. The suite uses **hoa_native_test_20261020_readiness**, never `hoa_system`; actual workflow acceptance remains pending until results are recorded. No Windows service was installed or existing database service stopped.
- Delivered review materials: `hoa-approval-package.md`, `security-recovery-handoff.md`, `native-mysql84-acceptance.md`, and `localization-coverage.md`. HOA approvals and independent host/credential recovery remain **awaiting human approval / externally blocked**.
- No `.env` edit, live migration, production account provisioning, real notification, credential rotation, push or deployment is included.

### Continued verification evidence

- Final dictionary inventory: **963 matching keys**; scanner reports **40 static Blade candidates** and no missing literal translation calls or unwrapped validation-message candidates. This scanner is not exhaustive; see `localization-coverage.md` for retained official-document, branding and unused-example text and remaining dynamic/manual review.
- Final focused SQLite selection after copy changes: **32 passed, 2,126 assertions** (77.11 seconds). Final production Vite build passed (61 seconds), retaining the known public hero-image warning. Pint and `git diff --check` passed; syntax parsing completed without failures across **404 PHP files**. Composer manifest validation and Composer/npm advisory checks passed with no known advisories at the recorded checkpoint.
- Firefox acceptance: **4 passed** (5.3 minutes), covering Cash-only form/admin complaint-creation denial and Owner/Co-owner/Tenant read-only household boundaries. WebKit is being checked separately; installed engines alone are not acceptance evidence.
- First complete native **MySQL 8.4.9** run: **338 passed, 1 failed, 3,573 assertions** (667.18 seconds). The failure is a confirmed **test fixture defect**: `LEGACY-` plus a full MD5 created 39 characters for the existing 32-character `service_requests.ticket_number` column. The fixture now uses a 24-character hash suffix (31 characters total). No schema widening or record repair was made; a focused native migration/concurrency rerun is required and recorded separately below.
- Interrupted/earlier browser runs remain diagnostic evidence, not completed passes. The later localized-feedback Chromium run passed; other browser scenarios require their own final results.
- After shortening the synthetic legacy ticket, native MySQL document/migration and multi-process payment-concurrency recheck: **13 passed, 70 assertions** (188.42 seconds). SQLite migration/localization recheck: **8 passed, 1,983 assertions** (8.34 seconds). A final full native run is in progress; the earlier complete run is not relabelled as green.
- WebKit acceptance: **4 passed** (1.9 minutes), matching the Firefox payment/complaint-denial and three household-role cases. Chromium localized contact submission passed (11.8 seconds), including persisted success and no false failure. Project-modal testing initially asserted visibility before the pending Livewire response returned; the harness now awaits the specific action response and allows render completion, without weakening field or authorization assertions.
- Further modal diagnosis: the captured project screenshot/accessibility tree confirmed that the dialog content was present; filtering the outer modal by its bounding-box visibility was inappropriate for this Filament container. The test now checks the named dialog's actual input visibility. Reopen confirmations correctly use the `alertdialog` role. Corrected Chromium modal selection: **2 passed** (2.2 minutes), including unsaved project text during locale change, keyboard cancellation/focus restoration, no serious/critical Axe findings on that modal, and assigned-staff finalized-record denial without actually reopening/mutating the complaint. A clean nine-case run is recorded separately below when complete.

### Release gate states (not a production release authorization)

| Gate | Current state | Required closure evidence |
| --- | --- | --- |
| Translation infrastructure and covered copy | Implemented; focused tests passed | Manual inventory of dynamic/default labels and remaining static candidates |
| Filipino wording | Awaiting human approval | Named reviewer, revision, corrections and approval date |
| Wider browser acceptance | Partially tested | Engine/workflow-specific results; remaining modal/mutation/restore flows must not be inferred |
| Native MySQL 8.4 | Locally tested: 339 passed on 8.4.9 | Trusted-host release rerun after incident recovery |
| HOA template/signatory/RBI approval | Awaiting human approval | Documentary decisions and authorized application audit confirmations |
| SEC-01 host and credentials | Externally blocked | Independent incident owner/rebuild/rotation/recovery sign-off |
| Live schema application/backfill | Not executed; separate approval required | Reviewed backup, migration rehearsal, deployment approval and dry-run backfill review |

Evidence files are local ignored diagnostics under `storage/logs/readiness-*`; browser traces under `test-results` may include session data and must remain private. Test fixture records and accounts are synthetic and restricted to explicitly disposable databases.

Final full native MySQL **8.4.9** verification passed **339 tests, 3,575 assertions, no skips** (623.39 seconds). This includes both SQL-only payment-concurrency cases skipped by SQLite and the corrected populated grandfathering migration test. No live database/schema/ledger was involved. This closes the local native-engine execution gap, not the independent host-security release gate.

### Final browser and cleanup record

- Chromium combined nine-case selection: **8 passed, 1 fixture-setup failure** (5.9 minutes). The failed payment case had not reached its payment assertions: it timed out resetting an administrator's language preference left by an earlier scenario while dashboard requests were pending. The setup now resets locale through the existing CSRF-protected endpoint before edits and reloads only during fixture initialization. Dedicated locale/modal cases continue exercising the real selector without reload or lost input.
- The first setup retry exposed a strict Playwright locator matching the panel's two CSRF meta elements; selecting the first matching token corrected the test locator. Final targeted Chromium payment/complaint-denial retry: **1 passed** (59.2 seconds). Earlier failures remain in diagnostic logs; no clean nine-case combined run is claimed after this correction.
- Passing final evidence therefore covers **9 distinct Chromium cases, 4 Firefox cases and 4 WebKit cases** across the recorded selections. Do not sum earlier overlapping retries. Chromium covers live invalid/corrected profile feedback, Cash-only recording UI/admin complaint denial, unsaved project modal text/language change/keyboard focus/Axe, three household roles, localized contact persistence/reset, and assigned/unassigned finalized complaint controls. This is not every financial mutation, restoration, approval, export or responsive state on every engine.
- Final browser test JavaScript syntax and `git diff --check` passed. Final Pint check passed. Production build and 404-file PHP syntax results are recorded above; no source dependency lockfiles or `.env` were changed for this continuation.
- The standalone native MySQL test instance on 33184 was shut down after confirming version 8.4.9; XAMPP's separate MariaDB service was left running. The audit-owned browser server on 8015 is stopped after verification. Disposable data directories/SQLite files and ignored logs remain for review; no historical records or user database files were removed.
- External gates remain open: human Filipino wording review, authorized HOA signatory/template/RBI decisions, independent host/credential incident recovery, and trusted-environment acceptance before production release. Full legacy/dynamic-copy and exhaustive cross-panel/browser coverage are not certified.

### Follow-up: localized report filters and workflow messages

- Confirmed defect: the report status dropdown translated the submitted option value as well as its visible label. In Filipino this submitted values such as `Nalutas` instead of the catalog's canonical `Resolved`. Kept canonical values, added explicit label markers and a Livewire regression test exercising the actual Filipino-rendered form and preview action.
- Covered the actual lowercase `complaint` and `request` values emitted by the workflow notification observer, rather than relying only on the previous test's `Complaints` label. Added subject/body assertions retaining original references.
- Localized report search placeholders, All scope presentation and summary labels without changing report queries, calculations, row content or export schemas. Dictionaries now have **977 matching keys**; no parity errors. The scanner still reports 40 manually reviewable candidates and does not prove complete coverage.
- Focused reporting/readiness suites: **28 passed, 2,162 assertions** (14.57 seconds). Full Pint, PHP/JavaScript syntax and `git diff --check` passed; import ordering was corrected by Pint. Production Vite build passed, retaining the previously documented runtime hero-image warning.
- Added a browser regression for Filipino canonical filtering, unchanged complaint subject and preview-close focus restoration. Chromium: **1 passed** (1.8 minutes), on a fresh disposable SQLite database. Further engine/full-suite results are recorded below after completion.
- Additional engines for the new report scenario: Firefox **1 passed** (2.0 minutes), WebKit **1 passed** (1.2 minutes), serially against the isolated server. Firefox logged a dynamic-chart import error; WebKit logged navigation-time access-control/module-import errors. The report assertions passed; diagnostics remain open investigation items. The chart file exists and HTTP logs include successful 200 deliveries, which does not prove why the client imports failed. These are not silent unconditional passes.
- Final full SQLite suite: **339 passed, 2 SQL-only skips, 3,602 assertions** (366.36 seconds), evidence `storage/logs/readiness-continuation-phpunit.txt`. Final standalone readiness suite: **9 passed, 2,020 assertions**. The skips remain the SQL-only concurrency cases; the earlier native MySQL 8.4.9 result is recorded above and was not rerun for this presentation-only increment.
- Localization scanner completed with exit 0, 977 matching dictionary keys and 40 review candidates; output retained in `storage/logs/readiness-localization-continuation.json`. Full Pint, production build and final diff/syntax checks passed. No dependency manifests/lockfiles changed; prior dependency-audit evidence is not represented as a new audit run.
- The audit-owned server on 8016 is stopped (no listener); the user's server on 8000 was not stopped or reconfigured. The disposable SQLite file and ignored test diagnostics are retained.
- No `.env`, historical data, database schema, official documents or permissions changed. No live migrations, external notifications, deployments or pushes performed.

### Original-layout Residency template (2026-10-20)

- Implemented the user-approved fixed-background approach, not an HTML recreation. Microsoft Word exported the supplied DOCX read-only to the bundled one-page Letter reference. The original DOCX SHA-256 remains `b436ba82870db95049480cec8c379a33377244c1f7ab63c50ce04f62eb62479d`; the corrected reproducible export script also passed a second export to an ignored review artifact.
- Added FPDI **2.6.9** and TCPDF **6.11.4**, pinned through Composer. The renderer overlays only the six mapped blanks, leaves the phase suffix and printed 2026 intact, rejects overflow/control characters/incomplete registered address, verifies private background checksums/dimensions and produces non-interactive PDFs. The original signatory/officers/logos are not edited or used to provision accounts.
- Added an additive template `renderer`/`layout` migration (defaulting existing versions to `text-v1`), private content-addressed backgrounds, immutable layout/value snapshots, year/signatory approval gates and a signatory-row lock during issuance. Existing numbering, request locks, historical PDFs, text templates and download permissions remain in use. Failed issued-output storage is cleaned up and rolled back.
- Admins can review the watermarked reference and explicitly register an unapproved original-layout draft through Certificate Templates. Separate HOA approval and exact printed-signatory authorization are required before activation. No live template was registered/approved, no signatory was configured and no live migration was applied. Deployment/review/rollback instructions: `original-residency-template.md`.
- Engineering visual check: imported background retains identical extracted text/Letter geometry; source/import raster color differences are at most 2/255, not pixel/byte identity. Comparing populated and unpopulated imports found **zero changes outside mapped blank areas** (2pt antialias allowance); Unicode extraction, no interactive widgets, and the complete draft watermark passed. The automatic TCPDF attribution was removed from page content. Human Word/PDF side-by-side approval and printer/viewer acceptance remain pending.
- Full SQLite regression: **352 passed, 2 SQL-only skips, 3,691 assertions** (367.98 seconds), evidence `storage/logs/original-residency-full-sqlite.txt`. The newly added certificate SQL-only race was added after this full run's discovery; it separately skipped on SQLite as intended and passed on native MySQL below. No new unexplained regression is recorded.
- Native MySQL **8.4.9**, fresh standalone loopback 33185, newly named `hoa_native_test_residency_20261020`, guarded opt-in: **45 passed, 183 assertions** (310.50 seconds), including original-template tests, populated/additive migration compatibility, legacy certificate workflows and both payment races. Separate new certificate race: **1 passed, 7 assertions** (28.73 seconds): two processes issuing one approved request produce one issued certificate, one authorization rejection and one PDF. Evidence: `original-residency-native-mysql.txt` and `original-residency-concurrency-mysql.txt` under ignored storage logs. The standalone instance was shut down; the ordinary XAMPP service/database was untouched. This is local evidence, not trusted-host incident closure.
- Chromium original-template browser workflow: initial outer-modal visibility assertion failed because Filament's outer alertdialog has no layout box; corrected the test to assert the visible switch, without weakening registration/approval assertions. Final retry **1 passed** (50.8 seconds), covering private reference/download headers, 320/768/1365px overflow checks, unapproved registration, private draft PDF and no serious/critical Axe findings on the exercised page. Fresh SQLite fixture and separate `LARAVEL_STORAGE_PATH` isolated all browser uploads/backgrounds/logs; no ordinary private files were used. No Firefox/WebKit run is claimed for this new workflow.
- Focused certificate/readiness regression checkpoints passed (**51 tests / 2,218 assertions** before the final additive-migration case; **24 tests / 118 assertions** for original/Phase2 document checks afterward). Full Pint, targeted PHP/JS syntax, Composer validation/audit, dictionary parity (**992 keys**) and production build passed. Build retains the previously documented runtime hero-image warning. Existing localization/host/HOA release gates remain open; no push/deployment or real notification occurred.

### Certificates-only integration (supersedes the separate management-page workflow)

- Removed the standalone Filament Certificate Templates page/view/navigation. `/admin/certificate-templates` now returns 404. Source/signatory confirmation and reference review are actions on `/admin/certificates`; there is no Residency text editor or replacement-design workflow. Matching original source/version rows are reused internally; historical records, PDFs and relationships are retained. Existing Cohabitation source approval remains available inside the certificate resource without bypassing its gate.
- New Residency issuance uses only the uploaded original background; there is no HTML fallback. It requires the actual Times New Roman Bold font at the original 12pt size, with no shrinking/substitution. Unsupported glyphs, overflow, missing font/source/schema and incomplete resident details produce readable errors. The source DOCX remains unchanged; phase suffix and printed 2026 remain intact. The optional visual checker passed original-font/size, Unicode, Letter geometry, complete watermark, no widgets, and zero changes outside mapped blanks (reference/import color rounding remains <=2/255).
- Added private 15-minute actor-bound previews and a nullable unique issuance key via additive migration `2026_10_20_000005_add_certificate_previews`. Confirmation locks and rechecks preview/data/permissions; matching retries return the same certificate. Approved Residency requests route through the same create/preview/confirm flow. Previewing allocates no number or notification. View/Print and Download serve the same stored PDF; finalized records are not regenerated. Expired preview records have a bounded pruning schedule that waits for the table to exist.
- Full SQLite run: **364 passed, 3 SQL-only skips, 3,788 assertions** (363.30 seconds), evidence `storage/logs/integrated-certificates-full-sqlite.txt`. Focused 65-test checkpoint passed (283 assertions); cross-module 36-test checkpoint passed (100 assertions). Subsequent focused readiness/integration checks include added Cohabitation action and source-permission coverage; final results are recorded below. Full-suite totals are not represented as including tests added afterward.
- Native MySQL **8.4.9**: **26 passed, 111 assertions** (187.27 seconds), evidence `storage/logs/integrated-certificates-native-mysql.txt`. This used only a newly named disposable `hoa_native_test_integrated_certificates_20261020` database on standalone loopback port 33186. Concurrent confirmation of one approved-request preview returns the same issued certificate to both processes, with one stored PDF/record. The standalone test instance is shut down; XAMPP/live data were untouched. This is local evidence, not host-security sign-off.
- Integrated browser scenario: Chromium **1 passed** (2.3 minutes), Firefox **1 passed** (2.1 minutes), WebKit **1 passed** (1.2 minutes), serially against isolated SQLite/private storage. Each exercised removed-page 404, original source preview, synthetic source/signatory confirmation, homeowner selection, completed preview, issuance and inline View/Print. Mobile/tablet/desktop overflow and bounded Axe checks passed on exercised screens; no broad accessibility certification is claimed. The test server on 8018 is stopped. No real source approval or notification was performed.
- The proprietary font is not committed/distributed. Deployment must supply a legitimately licensed Times New Roman Bold file (Windows local acceptance used the installed original font). Font-dependent PHP checks explicitly skip if absent; the browser case requires `HOA_BROWSER_RESIDENCY_FONT_AVAILABLE=true`. Such CI skips are not document acceptance. Documentation now uses only the certificate resource. English/Filipino dictionaries have **1,024 matching keys**; editorial approval remains pending.
- Composer validation/security audit, production build and formatting/syntax checks passed at recorded checkpoints; final checks follow below. No `.env` change, live migration, actual HOA approval, push, deployment or historical-document rewrite occurred. The newly required additive preview migration must be reviewed/applied through the deployment procedure, never `migrate:fresh`. Independent security recovery and real HOA authorization remain release gates.
- Final focused integration/readiness checks: **23 passed, 2,168 assertions** (35.08 seconds), including added stale-preview viewing protection and existing Cohabitation approval within the certificate list. Final production build passed (16.31 seconds), retaining the documented runtime hero-image warning. Final full Pint, JavaScript syntax, dictionary parity and diff checks passed. Test ports 8018/33186 have no remaining listeners; ignored synthetic fixtures/logs are retained for review.

### Cohabitation request draft guidance (2026-10-22)

- **Implemented:** removed unusable Cohabitation request PDF-preview buttons;
  approved admin/staff requests use **Prepare certificate** and the existing
  verified-details form. Bookmarked links redirect approved managers to their
  own panel, otherwise show private English/Filipino status guidance. Homeowner
  request details retain saved certificate downloads. No repeated source approval.
- **Related confirmed repair:** homeowner request tables now eager-load the
  relationship needed by their policy, avoiding a strict-lazy-loading exception
  reproduced by multi-record tests.
- **Tested:** focused **11 tests / 313 assertions**; full SQLite **479 passed,
  six native-only skips, 5,336 assertions**, no failures. Pint/syntax checks passed;
  Vite build passed in 18.02 s with the existing missing hero-image warning.
- Browser presentation evidence, exact changed modules and limitations are in
  [the focused audit](cohabitation-request-guidance.md). Native MySQL concurrency
  and live authenticated browser issuance were not reverified by these SQLite
  and rendered-page checks. Existing human/security release gates remain open.
- No migration, live-data/source-approval change, historical regeneration,
  external notification, account provisioning, push or deployment.
- Final rendered-page browser run: **6 passed**, Chromium/Firefox/WebKit,
  218.884 s; responsive/keyboard, locale and actual light/dark Axe checks. The
  first run was **5 passed / 1 WebKit keyboard failure**; adding a normal-order
  tab stop fixed the link without weakening its assertion. These are synthetic
  rendered-page checks, not new live authenticated browser acceptance.
- Final full SQLite rerun after the keyboard change: **479 passed, six native-only
  skips, 5,336 assertions**, 573.440 s; zero failures/errors. Evidence:
  `storage/logs/cohabitation-request-guidance-full-final-20261022.*`.

### Staff/admin service-request save repair (2026-10-22)

- **Implemented:** centralized transition rules, stage-appropriate status choices,
  localized next-step guidance, domain-error mapping to `data.*`, and refreshing
  the bound model after a successful locked save. Processing -> Approved can be
  performed without manually reloading the edit page.
- **Cross-module protection:** certificate requests complete through issuance;
  manual completion is rejected by the update action and a model backstop
  requires a linked issued certificate. Grandfathered non-certificate services
  retain manual completion. Existing permissions/ordinary edits are unchanged.
- **Tested checkpoint:** **34 focused tests / 580 assertions**, including the
  stale-form regression initially reproduced as a failure. Scoped Pint/syntax
  checks and production build passed. Full/browser results are retained in
  [the save-workflow audit](service-request-editing.md).
- No new migration, live status change, historical repair, source confirmation,
  real account provisioning, external notification, push or deployment. Browser
  fixtures use a separately named disposable SQLite database and private runtime.
- Full SQLite regression: **491 passed, six native-only skips, 5,567 assertions**,
  1,235.255 s, no failures/errors (`storage/logs/service-request-editing-full-20261022.*`).
  Initial live Chromium checks timed out before the edit form; they are retained
  as failures, with the separate serial retry documented in the focused audit.
- **Browser acceptance blocked:** both initial Chromium cases timed out before
  editing; both serial retry cases also exceeded navigation waits at the request
  list. No browser save/refresh, responsive or Axe pass is claimed. Firefox/WebKit
  were not run. The local list-loading timeout remains an investigation risk.
  Both owned test servers on 8031 were stopped; normal services are untouched.
