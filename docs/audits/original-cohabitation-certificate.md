# Original-document Cohabitation integration — 2026-10-21

## Hide the confirmed-source notice on daily creation (2026-10-22)

The user accepted hiding the informational Confirmed banner on `/admin/certificates/create`.
The existing Cohabitation source-status field now renders only when setup needs attention:
**Ready for review** and **Awaiting corrected document** guidance stay visible. This is a
presentation-only visibility condition; the shared status view and backend source approval,
permissions, preview validation, snapshots and issuance checks are unchanged. No live source
record or saved PDF was altered, and no migration or frontend asset rebuild is required.

Regression coverage checks both administrator and staff creation forms: confirmed status
remains true but its field/message are absent, while unconfirmed and missing-source guidance
remains visible. Existing private-preview, approval, source-version and issuance tests remain.
Focused SQLite: **36 passed / 299 assertions**, 108.535 seconds
(`storage/logs/cohabitation-confirmed-notice-focused-20261022.txt`). The initial run was
unusually slow but completed successfully; no application process was stopped. A separate
isolated-storage diagnostic rerun also passed all **36 tests**, exit code 0
(`storage/logs/cohabitation-confirmed-notice-isolated-20261022.txt`); these are the same
tests run twice, not 72 distinct tests. Two-file
Pint, PHP syntax and scoped diff checks passed. No browser, native MySQL or full
application suite is claimed for this small visibility change.

## Explicit local source confirmation recorded (2026-10-22)

This supersedes the pending **application source confirmation** below for the corrected
Cohabitation source only. After explicitly confirming the document/signatory and supplying
`admin@southville.test`, the user requested recording that account as the approver.

- Verified the existing account is active, non-deleted, an HOA administrator and authorized
  for `manage_settings`. No account was created or permission changed.
- Confirmed **JOSEPHINE D. ALARZAR / SHOAi PRESIDENT**, printed year **2026**, against the
  exact corrected DOCX/PDF/font checksums. The unchanged uploaded original checksum was
  rechecked. Current PDF SHA-256 remains
  `0ea3553a9ee40357f1a39dafe138f1a55dd56cb8de8908d32a278f9ebfb79396`.
- Used the existing `CohabitationCertificateSource::confirm()` service in an outer transaction,
  retaining permission checks, locking, immutable version history and `AuditObserver`.
  This was a user-delegated **CLI operation**, not an interactive administrator login.
  Audit attribution uses the explicitly designated account and `panel=system` truthfully.
- Application database: local `hoa_system`. Template **ID 4, version 1** changed from
  Draft to Approved at **2026-10-22 08:59:31 Asia/Manila**, approved by user **ID 1**
  (`admin@southville.test`). Immutable approval audit entry: **ID 123**.
- Private pre-change source metadata backup:
  `storage/app/private/operations/cohabitation-confirmation-20261022-085924-87ebd28c-before.json`.
  Only approval fields on the target draft changed; its source map did not. Other existing
  Cohabitation versions were compared with the backup and are unchanged.
- A separate read-only process verified persisted **Confirmed** status, approving account,
  audit actor/panel, approval timestamp and the private background's exact PDF checksum.
  Evidence: `storage/logs/cohabitation-approval-result-20261022.txt` and
  `storage/logs/cohabitation-approval-verification-final-20261022.txt`.
- Initial read-only database connection attempts failed; they made no approval writes.
  A bounded, process-local connection timeout was used for subsequent checks, which passed.
  The database service, `.env`, credentials and persistent settings were not changed.
- The initial post-check mistakenly compared the intentionally promoted draft as though
  it were an unchanged previous version. The verifier was corrected to allow only the
  approval fields on that target and compare its source map separately; the final check
  passed. Initial diagnostics remain in `cohabitation-approval-verification-20261022.txt`.
- The one-off helper is private under `storage/app/private/operations/`; it is not an HTTP
  endpoint, login bypass or new management page. PHP syntax checks passed. No application
  behavior changed, so the earlier test results are not presented as newly rerun suites.

This explicit request authorizes this local source confirmation only. No independent signed
HOA resolution or physical-print acceptance was supplied; neither is fabricated. Other source
approvals, provisional RBI, Filipino review and independent host-security recovery gates remain
separate. No certificate was issued, historical document regenerated, financial operation run,
migration applied, notification sent, push or deployment performed. Refresh the creation form
and create a fresh preview before issuance; source confirmation need not be repeated for this
same version.

## Authorized signatory punctuation correction (2026-10-21)

The user specified **JOSEPHINE D. ALARZAR** and answered yes to replacing the printed
`D,` with `D.` in the separate corrected source. This narrow correction is implemented;
it is not recorded as official HOA/source approval. An existing approving administrator
account is still required; none was inferred or impersonated.

- Original upload SHA-256 remains
  `9b572c18fc08ecc0edc909dced3069b3ead51f29c1be6beb6737cc0a3a0fe436`.
- Updated corrected DOCX:
  `44e225a6960a19e48249fe00a3d405b909c9c1a8ef57a9adfe266859e549284a`.
- New native Word PDF:
  `0ea3553a9ee40357f1a39dafe138f1a55dd56cb8de8908d32a278f9ebfb79396`.
- The previous corrected DOCX, Word PDF, layout and README are preserved unchanged in
  `resources/certificates/cohabitation-2026/history/phase-only-20261021/`.
  No existing source record, saved certificate or financial record was changed.
- Only the phase phrase and exact signatory-initial text run differ from the raw DOCX;
  all 19 other ZIP parts remain identical, including images and formatting. The internal
  legacy bookmark name is deliberately unchanged; it is not visible printed text.
- Native Word exported the source read-only with macros/link updates disabled. Owned Word
  process 17732 exited normally; the user's pre-existing Word process 16540 was left open.
- The new PDF differs from the previous Word export only in the signatory region:
  **576 color channels**, bounding box `(126, 616)–(177, 625)` points at 72 dpi. Every
  extracted text character outside `D,` → `D.` is unchanged. Original Letter geometry,
  fonts, phase blanks and all other coordinates remain unchanged; no re-layout/redesign.
- File-only PDF checks pass: **1,365 changed channels** vs the initial Word export are
  confined to the two authorized phase/signatory regions; **9,936 populated channels**
  remain inside mapped blanks. Arial 12pt/baseline, Unicode, phase autofill twice,
  watermark and non-interactive output passed. See `cohabitation-signatory-pdf-check.txt`
  and `storage/framework/testing/cohabitation-signatory-review-20261021/`.
- Metadata now prints the corrected name and records previous source/PDF checksums.
  Existing source-version matching requires fresh confirmation; approval is not transferred.
  A regression test verifies old snapshots/PDF bytes and old approvals are unchanged,
  stale previews are rejected, and the corrected version needs its own confirmation.
- Focused SQLite: **34 passed / 270 assertions**, 31.918 seconds
  (`storage/logs/cohabitation-signatory-focused.txt`). Test approvals use fake storage,
  fake notifications and disposable SQLite only; they are not real HOA approval.
- Related Residency, integrated certificate, original receipt, shared-receipt, certificate
  workflow and Phase 2 document regressions: **102 passed / 595 assertions**, 107.502 seconds
  (`storage/logs/cohabitation-signatory-regression.txt`). Together, **136 targeted tests /
  865 assertions** passed; this is not the full application suite.
- Three-file Pint, PHP syntax, browser JavaScript syntax and scoped diff checks passed.
  Production Vite build passed in **20.61 seconds**
  (`storage/logs/cohabitation-signatory-build.txt`); the pre-existing missing
  `southville-community-hero.webp` warning remains unrelated and unresolved.
  The isolated browser-fixture setup command was rejected by tool policy before execution;
  no new browser server/database was created and no browser pass is claimed for this
  punctuation increment. Full SQLite/native MySQL, physical printing and cross-engine
  browser acceptance have not been rerun for this increment. Earlier results below
  apply to the earlier source and are not presented as fresh verification.

Changed scope: current/archived private source assets, manifest, copy-only correction and
file-only PDF checker/preview scripts, source/approval-history tests, browser expected name,
and audit documentation. No new migration, live approval, deployment or historical regeneration.

## Authorized phase-line correction (2026-10-21; supersedes the missing-source gate below)

The user authorized removing the printed A and repurposing its following blank as `Phase 1 - ____`,
then selected registered-phase autofill. A separate corrected original, native Word PDF and measured
blank map are now supplied. No further corrected-file upload is needed. **Real HOA source/signatory
approval remains pending.**

The original upload is unchanged (`9b572c18fc08ecc0edc909dced3069b3ead51f29c1be6beb6737cc0a3a0fe436`).
Corrected DOCX: `bb8b626f22c75950c4003ad6e8ac7e2ca4105a4b7146716de7031444da280e0b`;
Word PDF: `6cf0776aea29d1aaa03f49f81dbda0ad633ad872ed04d74ab9d9bf426aa7ee20`.
One literal text fragment in `word/document.xml` changes; all 19 other package parts match exactly.
The native Word exports have **zero changed pixels outside the phase-line region** (789 changed
color channels inside it). Letter dimensions, wording/logos/signatory/year and Arial 12pt body
are preserved. The original heading spelling is not corrected. Only separately owned Word processes
were used; the user's open Word instance was not closed or edited. A PowerShell export entry point
was blocked by local execution policy before opening Word; the native VBS/Word alternative succeeded
without changing that policy.

The form displays the registered suffix read-only and fills both original phase locations.
Missing/unresolved property identity still blocks generation. The obsolete Additional address detail
field/rule is removed from new submissions and the current map. Historical snapshots and optional
legacy map metadata remain readable; no saved PDF is regenerated. Forged phase/obsolete inputs
cannot change the authoritative registered address or enter the new certificate snapshot.

The source is **Ready for review**, not automatically confirmed. Private previews, source/signatory
approval, locked revalidation, immutable snapshots, idempotency and saved downloads are retained.
No migration, account/property repair, financial ledger write, live approval, external notification,
push or deployment is included.

`cohabitation-phase-pdf-check.txt` verifies the blank renderer preserves the Word background
(at most 2/255 antialiasing delta); populated values change only mapped blanks. Original Arial
size/baseline, Unicode, both phase values, watermark, Letter geometry and no form widgets passed.
These are technical checks, not HOA or physical-print approval. Source README and reusable
copy/export/preview/check scripts document provenance.

Initial focused regression: **26 passed / 193 assertions**. Expanded certificate/receipt regression:
**103 passed / 737 assertions**, 108.65 s (`cohabitation-phase-expanded.txt`), including the actual
corrected-source pipeline, missing-phase/forged-input checks and historical receipts. Build passed
in **15.41 s** (`cohabitation-phase-build.txt`); the existing missing hero-image warning remains.

Final verification for this correction:

- Full SQLite: **458 passed / 4,776 assertions / six native-only skips**, 472.98 s
  (`storage/logs/cohabitation-phase-full-sqlite.txt`).
- Native MySQL **8.4.9**, disposable `hoa_cohab_phase_correction_test_20261021`: **9 passed /
  78 assertions**, 201.31 s (`cohabitation-phase-native-preflight.txt`, `cohabitation-phase-native.txt`).
  Includes actual corrected-source processing and both original-document concurrent-issuance tests;
  this is focused native coverage, not the full native suite.
- Chromium: **2 passed**, 2.1 minutes (`cohabitation-phase-browser-v2.txt`), with no retries/skips
  in the successful run. Tests cover private source review, one-time test-only confirmation,
  registered/read-only phase, no obsolete address input, live error correction, private preview,
  official issuance and identical stored bytes for View/Print and Download. Ready-source dialog
  checks include 320/768/1440 widths, keyboard Close and explicit light/dark Axe checks with no
  serious/critical findings. Browser approval is confined to the guarded disposable fixture; it
  is not real HOA approval of the document or signatory.
- The first browser run failed twice while Livewire requests were still loading, including a
  30-second scenario budget. Scenario-local budgets and bounded waits were corrected, and a fresh
  separately named fixture was used after CPU-intensive backend runs ended. Failed screenshots/logs
  remain in `test-results`/`cohabitation-phase-browser.txt`; successful rerun artifacts use their
  own `storage/framework/testing/cohabitation-phase-browser-artifacts-v2` directory. Assertions
  for authorization, validation, saved documents and byte equality were retained.
- Pint: **11 PHP files passed** (`cohabitation-phase-pint-final.txt`), plus PHP syntax, browser
  JavaScript syntax and scoped diff checks. No additional application translations were required;
  stored document labels and original wording were not changed. Existing pending-source browser
  cases now explicitly require a deliberately unavailable-source fixture; missing-file behavior is
  also tested in PHP. The new browser fixture uses the real corrected artwork and confirmed
  synthetic property records rather than assuming the source is still missing.
- Owned browser/MySQL listeners on ports 8025/33189 were stopped after verification. The original
  DOCX checksum was rechecked, and the user's existing Word process remained open. No live database,
  source approval, historical document or normal application/database listener was modified.

Changed modules: corrected-copy/native-export and PDF-check scripts; private DOCX/PDF/field map;
shared Cohabitation validator/value mapping/source-map validation; existing certificate form;
source/phase/legacy compatibility tests and isolated browser fixtures; source README and audits.
No new migration or template-management page was added. Physical-print/HOA approval, cross-engine
browser acceptance and independent host-security release gates remain outside this correction.

## Setup and issuance simplification (2026-10-21)

- Document setup now has one Cohabitation action rather than separate review and confirmation
  entries. It displays **Awaiting corrected document**, **Ready for review**, or (in the creation
  form) **Confirmed** from a shared, read-only readiness check.
- Missing corrected DOCX, Word-exported PDF and reviewed blank mapping are listed explicitly.
  The guidance modal offers only Close: there is no review link, signatory toggle or confirmation
  submit until the required assets/schema/font are ready. Opening it does not create source,
  preview or certificate records or copy a background file.
- Once ready, one modal contains the private source-review link, printed signatory/year,
  existing review/signatory acknowledgements and approval evidence. Confirmation remains an
  administrator-only, audited, one-time operation for that exact source version. The setup
  entry hides after confirmation; changing the source requires fresh confirmation and does not
  change the previous approved version. No approval is inferred from a file or automatically run.
- The normal certificate submit button is **Issue certificate**, following the existing completed
  preview. It submits directly without another confirmation dialog; preview binding, transactional
  validation and duplicate-issuance protection are unchanged. Receipt confirmation is unaffected.
- The private Cohabitation source-review URL retains HTTP 409 when unavailable but now renders
  a safe, localized guidance page with a **Back to Certificates** link instead of a debug exception.
  Staff/homeowner denial remains enforced before rendering. English/Filipino status and guidance
  copy share the same marked Blade component in setup, creation and the unavailable-source page.
- No migration, original-document change, live approval or historical PDF regeneration is included.
  The corrected original with `Phase 1 - ____` and its reviewed export/mapping are still missing.

Verification for this increment:

- Setup interface: **7 passed / 76 assertions**, 7.68 s
  (`storage/logs/cohabitation-setup-interface-v3.txt`). Two initial test-only failures arose from
  Filament's partial-modal response not being included in the cached page HTML; attempting to
  render the entire modal outside Livewire then failed because it lacked component context.
  Tests now inspect the mounted schema/status component; actual modal rendering is browser-tested.
- Full SQLite: **449 passed / 4,702 assertions / six native-only skips**, 401.79 s
  (`storage/logs/cohabitation-setup-full-sqlite.txt`). Includes missing/setup state, confirmation
  persistence, source-version changes, font/mapping failures, forged approval denial, private
  guidance, existing issuance/rollback/concurrency guards and related module regressions.
- Final focused SQLite regression: **50 passed / 2,650 assertions**, 83.59 s
  (`cohabitation-setup-final-focused.txt`), after adding confirmed-state/Filipino rendered-output
  coverage and the button contrast fix. This is separate from the full run above, not another
  full-suite result. The initial Filipino fixture attempted mass assignment to protected
  `preferred_locale`; the test now uses an explicit internal assignment without opening model
  mass-assignment permissions. The corrected pair also passed separately (13 assertions).
- Native MySQL **8.4.9** preflight and focused/concurrency regression: **41 passed / 264 assertions**,
  629.40 s, on disposable `hoa_cohabitation_setup_test_20261021`, localhost port 33188
  (`cohabitation-setup-native-preflight.txt`, `cohabitation-setup-native.txt`). Includes both
  original-document concurrent-issuance tests. The final two presentation-only cases were added
  later and are covered by the final SQLite selection, not claimed as part of this native run.
- Production build passed in **20.24 s** (`cohabitation-setup-build.txt`). Existing hero-image
  warning remains. Pint checks passed for eight changed PHP files (`cohabitation-setup-pint-complete.txt`);
  PHP syntax, three browser-script syntax checks and scoped tracked-file diff checks passed.
  English/Filipino dictionaries have **1,161 matching keys**. The source-only localization scan
  still reports 56 review candidates elsewhere; parity is not complete editorial approval.
- Browser harness initial attempt was stopped: it used the wrong server working directory and
  could not locate `public/index.php`. A fresh isolated fixture/server uses `public` as its working
  directory. The first fixture was also slow because of the inherited hashing cost; the replacement
  sets the test-only hashing cost. Neither fixture uses the live database or sends external mail.
- Browser first combined run: two passed/two failed, with an admin Livewire-response timeout and
  a test asserting visibility of Filament's zero-box dialog wrapper rather than its visible window.
  The test now checks the window; the header case waits for the setup dropdown to close before
  clicking another action. A subsequent Axe run found real **3.73:1** white-on-blue contrast on
  New document; it now reuses the existing darker-blue primary action class, with no global
  palette redesign. Theme checks explicitly synchronize Filament's theme selection and verify
  the root class before running Axe, rather than sampling mixed preference/store states.
- Corrected targeted browser runs: admin header passed (35.4 s); setup dialog passed (34.8 s,
  37.4 s runner total; `cohabitation-setup-browser-v5.txt`), including 320/768/1440 widths,
  keyboard Close/return navigation and no serious/critical Axe findings in explicit light/dark
  themes. The earlier combined run also passed staff header and the Cohabitation form, including
  real-time correction and unavailable-source protection.
- Final combined Chromium run: **4 passed**, 1.7 minutes
  (`storage/logs/cohabitation-setup-browser-final.txt`), with no retries/skips in that run. Covers
  administrator/staff headers, Cohabitation form/live errors, setup requirements, missing-source
  submission restrictions, private guidance/return navigation, responsive widths, keyboard actions
  and serious/critical Axe checks in explicit light/dark themes. Earlier failures are retained above
  rather than counted as unconditional passes. The isolated listeners on ports 8024/33188 were
  stopped after verification; the user's normal application/database services were not stopped.

Changed modules: shared Cohabitation source readiness; existing Certificates list/create/form;
private draft-review controller; shared status and unavailable-source Blade views; English/Filipino
dictionaries; three PHP feature suites and three browser specifications; this report and the
revision checklist. No database migration was added.

Remaining acceptance limits: the corrected original and real Word/PDF/mapping review are still
unavailable; synthetic pipeline fixtures are not official artwork or HOA approval. No new actual
print-fidelity, Firefox/WebKit, automatic OS-only theme-following, full native-suite or independent
host-security acceptance is claimed for this UI increment. Prior historical PDFs remain untouched.

## Delivered scope and source gate

The existing Cohabitation catalog type now uses the same original-PDF pipeline as Residency:
homeowner → declarations → private watermarked preview → confirmed issuance → saved PDF.
No separate management page, replacement artwork, new catalog type or migration is introduced.

The upload `Cohabilitation Certificate.docx` is preserved unchanged at
`resources/certificates/cohabitation-2026/original-unadjusted.docx`, SHA-256
`9b572c18fc08ecc0edc909dced3069b3ead51f29c1be6beb6737cc0a3a0fe436`.
Its literal `Phase 1-A` conflicts with the requested dynamic phase blank. The user explicitly
chose to supply a corrected original, not erase that printed A. Its checksum is rejected as
an activatable source even if a manifest accidentally claims its phase is blank.

**Pending:** corrected original with `Phase 1 - ____`, faithful Word-exported PDF, measured
blank mapping and visual/font review. None has been invented. The source directory README
documents the required manifest and field keys. Once supplied, export read-only with macros
disabled, verify checksums and Word/PDF fidelity, then request explicit HOA confirmation.
If faithful conversion is unavailable, request a manually exported Word PDF. No conversion
or physical-print fidelity result for the future corrected original is claimed.

## Form, validation and privacy

- Registered first-person name/birth date/address are prefilled and read-only where available.
  A registered second person is an explicit same-property selection, not a name-based link.
  Otherwise verified manual partner details remain certificate-only.
- Birth dates, administrator-entered address continuation, completed years and either named
  requester are required. Actual issuance day/month and confirmed registered phase are derived.
  Dates print as month/day/year components. Years are nonnegative whole completed years and
  cannot exceed either completed age; no minimum-age policy is added or move-in inference used.
- Shared rules run during debounced Livewire updates and again during locked issuance. Field
  errors preserve input; relevant changes invalidate the actor-bound 15-minute preview.
- Approved requests retain their saved names/address and collect missing declarations here.
  They are not rewritten. The existing schema requires account birth dates; manual date fallback
  principally covers unregistered/request subjects and does not make account fields nullable.
- Only address fields enter the address snapshot—not emergency contacts, landlord details or
  verification-document paths. Manual declarations do not update accounts or create relationships.
- English/Filipino labels/messages were added. Stored certificate identity, historical content,
  printed source wording and names are not translated.

## Processing and authorization

`CohabitationCertificateData` centralizes declaration validation and mapped values;
`CohabitationCertificateSource` validates reviewed assets and immutable source confirmation.
The existing renderer/font/preview services now accept a type-specific Cohabitation profile.
Arial regular (`ArialMT` in TCPDF's actual font metadata) is required, with checksums and no
substitution. Fit/glyph errors are attached to their mapped fields; no shrinking/truncation occurs.

Source review/one-time confirmation remains in Certificates → Document setup. The printed
Cohabitation signatory is confirmed per source version, never written over Residency's global
settings. Approved versions remain immutable. Generic HTML Cohabitation definitions cannot be
used for new official issuance; historical issued files remain readable through existing rules.

Active admin/staff with `manage_requests` may prepare/issue; only administrators with
`manage_settings` may confirm the source. Ownership/download permissions remain unchanged:
being named or sharing a property grants no additional document access. Issuance rechecks
source, actor, people, property, date and declarations under locks, uses the existing unique
confirmation key, and rolls back numbering/records/files on failure. Print/download serve one
finalized non-interactive file. Existing Residency and Resibo Butaw paths remain supported.

## Investigation and verification log

- Initial related regression: 37 passed, 7 obsolete generic-source/interface expectations failed.
  Updated those assertions to original-source confirmation and no HTML fallback; authorization
  and historical-document assertions were retained.
- Initial new tests: 6 passed, 3 failed because licensed Arial's actual PostScript name is
  `ArialMT`, not `Arial`. The profile now accepts those exact regular Arial names, not substitutes.
- Two subsequent privacy-test failures were fixture guard conflicts: the prior admin remained
  logged in. Tests now explicitly clear conflicting guards before changing actors; denied-access
  assertions remain intact. A first translation build exposed two invalid new JSON values from
  a Windows Unicode pipe round-trip; repaired and JSON/build rechecked.
- Focused related regression: **53 passed / 405 assertions**, 105.39 s
  (`storage/logs/cohabitation-focused-v3.txt`).
- Expanded tests exposed a real approved-request form issue: refilling the form cleared required
  `document_kind`. Mount now sets `certificate` server-side. Hidden purpose metadata remains
  dehydrated so request previews and final issuance agree without printing an extra field.
- A test attempted a null account birth date prohibited by the current schema; it now exercises
  manual request-subject dates without weakening that database constraint.
- Expanded Cohabitation tests: **15 passed / 90 assertions**, 23.24 s
  (`storage/logs/cohabitation-expanded-v2.txt`), including private previews, approval, snapshots,
  idempotency, approved requests, overflow/glyph rejection, year/source checks, rollback and roles.
- Production build passed after JSON correction in **18.22 s**
  (`storage/logs/cohabitation-build-v2.txt`); existing runtime hero-image warning remains.
- Full SQLite initial regression: **443 passed / 4,639 assertions / six native-only skips**,
  468.49 s (`cohabitation-full-sqlite.txt`). A final full rerun follows the fingerprint repair below.
- Native MySQL **8.4.9** preflight passed against the new disposable
  `hoa_cohabitation_test_20261021` database. Its first focused run exposed JSON object-key
  reordering: an unapproved first preview falsely expired after persistence. Fingerprints now
  canonicalize associative keys (not list order), while accepting still-valid legacy hashes for
  existing 15-minute Residency previews. A dedicated SQLite/native regression covers reordering.
  The other native failure was a snapshot test comparing pre-storage PHP key order with MySQL's
  persisted JSON order; it now captures the stored snapshot before testing later immutability.
- Native rerun: **31 passed / 270 assertions / two SQLite-only DDL skips**, 204.15 s
  (`cohabitation-native-v2.txt`). This includes parallel Cohabitation and Residency confirmations,
  approved-request completion, shared saved receipts, permissions and rollback. This is focused
  native verification, not the full native-MySQL suite.
- Initial browser assertions missed required-marker/whitespace label differences; selectors now
  use their distinct accessible roles/names. The first receipt regression also hit a local PHP
  request timeout. These failed attempts are retained, not counted as passes.
- Chromium receipt regression passed on retry (1.6 minutes, `cohabitation-browser-v2.txt`),
  including confirmed generation and matching saved bytes across Certificates/Payments.
- Chromium Cohabitation pending-source workflow passed in **25.8 s**
  (`cohabitation-browser-v3.txt`): autofilled/read-only fields, verified manual details, years
  error/correction, requester selection, blocked issuance, source-review 409, keyboard preview,
  320/768/1440 layouts without page overflow, and light/dark Axe with no serious/critical findings.
  The fixture retains an unresolved legacy phase; required phase review also correctly blocks
  preview. Unit/native tests cover complete confirmed properties and synthetic official output.
- PHP syntax passed for **372 files** and English/Filipino key parity passed for **1,149 keys**
  per locale; browser JavaScript syntax and scoped diff checks passed. Final Pint/full results
  are recorded below when complete. Other browser engines and real corrected-document printing
  have not been verified.
- Full SQLite after the fingerprint fix: **444 passed / 4,644 assertions / six native-only skips**,
  324.41 s (`cohabitation-full-sqlite-final.txt`). Final review added a normalization regression:
  canonically equivalent Unicode/repeated spaces in legacy request names must not be rejected,
  while the request itself stays unchanged. Printed/metadata names now use the same normalized
  values. Final full/native runs including this last edge case are tracked separately below.
- Pint passed on all 20 affected PHP files and on the final normalization edits
  (`cohabitation-pint-final.txt`, `cohabitation-pint-normalization.txt`).
- Final native MySQL 8.4.9 run including Unicode/spacing normalization: **32 passed / 274
  assertions / two SQLite-only DDL skips**, 201.93 s (`cohabitation-native-final.txt`).
  The dedicated Cohabitation and Residency parallel-issuance tests passed. No MariaDB result
  is represented as native MySQL evidence.
- Final full SQLite run including all changes: **445 passed / 4,648 assertions / six native-only
  skips**, 419.76 s (`cohabitation-full-sqlite-complete.txt`). No remaining test failures in this
  run. Final scoped diff/syntax checks passed; no new migration was created or applied live.

Synthetic blank PDF fixtures exercise processing only. They are explicitly marked test-only,
live in isolated test storage, and are not replacement certificate assets or visual acceptance.

## Rollout and remaining gates

No new migration is expected. No live source approval, account provisioning, data repair,
historical regeneration, external notification, push or deployment is included. Test databases,
mail and private storage are isolated. Implementation/testing is separate from **awaiting HOA
source/signatory/physical-print approval** and the **independent host-security recovery gate**.
Do not mark the real original-document integration visually accepted until the corrected source
has been supplied and its actual generated outputs reviewed.

Owned browser (8024) and native MySQL (33187) test listeners were stopped and verified closed.
The user's normal application/database processes remain untouched. Disposable fixtures/private
diagnostics remain available; no real external notifications were sent.

Gate summary: code integration **implemented and tested** within the coverage above; corrected
source, real field mapping, Word/PDF/physical-print comparison and HOA signatory approval
**awaiting supplied document and human review**; production release **externally blocked** by
independent host/credential recovery. Synthetic test outputs are not official documents, and
passing these checks does not establish complete cross-engine/full-native/full-system acceptance.

## Service-request draft guidance follow-up (2026-10-22)

The request draft endpoint's unconditional Cohabitation 409 was a UI/workflow
mismatch, not a request for another source confirmation. Cohabitation no longer
offers an unusable request-level PDF preview. Approved managers use **Prepare
certificate** in their own panel; authorized pending/homeowner/bookmarked access
receives status-specific English/Filipino guidance. Residency previews, original
sources, approval records and saved documents are unchanged. See
[the focused audit](cohabitation-request-guidance.md) for changes, regression
evidence and browser/native-database coverage limitations. No migration or live
approval was performed for this repair.
