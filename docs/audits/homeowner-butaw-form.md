# Homeowner-based original Resibo Butaw form — 2026-10-21

## Implemented workflow

The subsequent [shared monthly receipt increment](shared-monthly-receipts.md) makes Payments serve these same saved files and adds a trusted payment-preselection shortcut.

Inside the existing Certificates creation page, choose **Monthly Dues Receipt (Resibo Butaw)**, then **Homeowner / liable property account**, then an **Accepted monthly Cash payment**. Review the automatically filled details, preview the completed original PDF, explicitly confirm generation, and use **View / Print** or **Download**. The two links serve the same saved document; generation does not create a payment, assessment or certificate number.

The homeowner selector refers to the payment's liable property account, not necessarily its actual payer. Tenant/co-owner payments remain attached to that liable account. Current resident accounts are searchable by name/address; archived liable accounts are offered only when they have an otherwise eligible payment and are labelled Archived. Searches/preloads are limited to 30 results with eager-loaded relationships.

The payment selector is disabled until a homeowner is selected and is filtered to that account. The form shows the liable name, actual payer, receipt number, payment date/method, actual amount paid, obligation billing month/year, and original property/collector snapshot. Display values are computed server-side and never accepted as editable receipt data. Changing homeowner clears payment, details, preview and generated-document links; changing payment invalidates preview and links. English/Filipino presentation labels use explicit translation markers without changing resident content or printed source wording.

When no qualifying payment exists, the form explains why a recorded payment is needed and links authorized users to the existing Cash-payment page. Missing schema/source confirmation is explained without creating records or approving the document automatically.

## Original source and financial protection

The unchanged uploaded single-slip PDF remains the only current source: `resources/certificates/resibo-butaw-pdf-2026/reference.pdf`, SHA-256 `ae0f3fb8ca5470aeebb97c22eec429536e0e71a7648ef0cf8f91cc389361dc30`.

Only amount, month, block, lot and recorded collector are inserted in existing blanks. Names and other contextual details are shown in the form/metadata only; no new printed fields are added. Printed 2026, exact Calibri sizing, original geometry/design, overflow rejection and unsupported-character rejection remain unchanged. Source confirmation stays a separate, one-time authorized administrator decision under the existing Document setup menu, not a new page or per-receipt approval.

Accepted Cash-only, monthly-2026, zero-penalty and verified collector/property eligibility remains authoritative. `ButawReceipts::prepare()` and `generate()` accept an optional expected liable-homeowner ID for backwards-compatible callers; the new form supplies it and the service checks it during preview and locked generation. Preview fingerprints now also include payment date/method so changed displayed details require a fresh preview. Previously saved PDFs are not modified. Receipt field errors retain required-field validation information instead of being indiscriminately remapped.

Payment-management authorization is rechecked before form actions and by the shared service. Actor-bound 15-minute previews, row locks, one-document-per-payment/source uniqueness, private files, failure cleanup and eligibility checks at download remain intact. Existing certificate/service-request paths and receipt history are preserved.

## Verification evidence

- Initial focused pass: 24 tests passed; two new assertions exposed loss of failed-rule metadata when remapping form errors. The handler now preserves already-correct receipt field exceptions.
- Related SQLite document regression: **96 passed, 2,749 assertions**, 280.38 seconds (`storage/logs/homeowner-butaw-focused-final.txt`). This run preceded the last three additional regression cases; final full results below supersede it.
- Subsequent receipt-only recheck: **28 passed, 195 assertions**, 31.71 seconds (`homeowner-butaw-recheck.txt`); payment-date stale-preview coverage was added afterwards.
- Repeated file-only PDF check passed: unchanged Letter background within 1/255 raster rounding; 2,190 changed color channels confined to mapped blanks; Calibri 11.04, Unicode, draft watermark and no interactive fields verified. This is layout equivalence for generated files, not byte identity; the private source-review endpoint remains byte-identical.
- Native disposable-database preflight confirmed **MySQL Community Server 8.4.9**, port 33185, database `hoa_homeowner_butaw_test_20261021`. Preflight is not a workflow pass. Native tests use a separate runtime/private storage root from both SQLite tests and browser fixtures to prevent fake-disk/font-cache interference.
- First browser attempt encountered a local PHP 60-second execution timeout while compiling/rendering the unchanged certificate list on a fresh isolated runtime. Its outcome and any retry are reported separately; no unconditional browser pass is inferred.
- Full SQLite suite: **413 passed, five native-only skips, 4,298 assertions**, 897.13 seconds (`storage/logs/homeowner-butaw-full-sqlite.txt`). The final product code was exercised; the subsequent native-JSON test assertion correction changes only how an unchanged historical manifest is compared.
- Final receipt-only SQLite recheck after that assertion correction: **29 passed, 197 assertions**, 60.38 seconds (`homeowner-butaw-recheck-final.txt`).
- Native initial related-document/concurrency run: **44 passed, one skipped, one failed**, 647.08 seconds (`homeowner-butaw-native-mysql84.txt`). The failure was a test defect: raw JSON strings differ because native MySQL canonicalizes object key order. Comparing all decoded/cast attributes preserves the complete immutability assertion. Receipt-only native recheck then **passed 28 tests, 194 assertions**, with the intentional SQLite-only transactional-DDL test skipped (81.87 seconds; `homeowner-butaw-native-mysql84-recheck.txt`). This includes the corrected historical-source test. Concurrent receipt generation passed in the initial run. An additional combined rerun became unresponsive and was interrupted; its empty `homeowner-butaw-native-mysql84-final.txt` is not acceptance evidence. No clean combined rerun is claimed.
- Production Vite build passed with captured exit code **0**, 44.89 seconds (`homeowner-butaw-build-final.txt`); the pre-existing missing runtime hero-image warning remains unrelated. Changed-file Pint, six PHP syntax checks and browser JavaScript syntax checks passed.
- Browser acceptance remains **incomplete**. Initial two-case run failed both scenarios (certificate-list PHP timeout; five-second wait while a payment update was pending). The next two-case run also failed (certificate-list timeout; keyboard focus attempted before the payment button was enabled). The harness now awaits the payment update/enabled state. Error traces additionally showed the HTTP SAPI using the default Blade-cache path rather than the requested private runtime. A fresh v2 fixture used `php -d variables_order=EGPCS -d max_execution_time=120 -S ...`, explicit `VIEW_COMPILED_PATH`, and a private runtime/session cookie. Its list case still timed out and the remaining long-running verification was interrupted when the local runner became unresponsive. Logs: `homeowner-butaw-browser.txt`, `homeowner-butaw-browser-retry.txt`, `homeowner-butaw-browser-final.txt`. These are **not passes** and the timeout cause is not fully resolved. The earlier generation scenario did exercise homeowner/payment selection, populated details, preserved English/Filipino values and account-change empty-state checks before its focus failure; final PDF generation/download was verified by Livewire/HTTP tests, not a successful browser run in this increment.
- Both browser fixtures generated **zero** receipt documents, verified directly against their separately named synthetic SQLite databases. No live database was used. Firefox/WebKit and a successful final responsive/keyboard/Axe workflow remain outstanding; prior-increment browser results are not presented as current acceptance.
- Audit-owned HTTP port 8021 and native MySQL port 33185 were verified to have no listeners after cleanup. Synthetic databases, logs and private fixture artifacts remain for investigation; no user database, account or original PDF was deleted.

## Changed modules

- Certificate creation schema/page: homeowner selection, scoped payment choices, read-only details, state resets and expected-account validation.
- Existing eligibility/receipt services: archived liable-account lookup, optional expected-homeowner context and payment-date/method preview fingerprinting.
- Receipt details/preview Blade views and English/Filipino dictionaries: readable responsive display and explicit translation markers.
- Receipt feature/concurrency and browser tests: ownership, sharing, privacy, stale-state, rollback/history and authoritative snapshot assertions; JSON comparison is database-portable.
- This audit, source-correction audit and phased revision checklist: implementation, actual results and remaining coverage.

## Rollout boundaries

No new migration is required. Only disposable synthetic databases were used. Native tests and the v2 browser fixture use separate runtime/private-storage roots; the first HTTP fixture's unexpected default cache path is recorded above, rather than claiming unconditional initial filesystem isolation. No live source confirmation, account/payment repair, financial/history rewrite, external notification, push or deployment is included. Existing source approval/font/setup requirements remain. Physical-print review and authorized HOA source confirmation remain human tasks; independent host-security recovery remains a production release gate.
