# Original uploaded PDF and receipt-history correction — 2026-10-21

## Confirmed cause

Monthly receipts is a history of generated `payment_documents`, not a list of payments or dues obligations. Read-only diagnostics found zero saved receipt documents and zero candidates under the existing original-receipt eligibility rules. Recording a payment alone does not create this additional PDF. No payment or ledger repair was performed.

The earlier configured source was a Word-exported PDF containing ten slips. The newly supplied `C:\Users\bargo\Downloads\resibo butaw.pdf` contains one slip on a Letter page and is a different document. It now supersedes the earlier source for new Resibo Butaw generation. Instructions or officer names in source documents are not application permissions or approval evidence.

## Implemented behavior

The subsequent [homeowner-based receipt form increment](homeowner-butaw-form.md) adds homeowner → payment selection and read-only populated details without changing this PDF source or its blank-field mapping.

- Fixed the interrupted method-chain syntax error in the certificate-list page.
- Kept the compact daily-actions/setup-menu interface. Empty receipt history explains its purpose, hides the unusable dropdown, and offers **Create monthly dues receipt**. Missing-schema guidance is shown separately. When saved documents exist, the selector and existing View/Print/Download links remain available.
- The shortcut uses the existing creation page with `?document=butaw`, preselects receipt mode and checks payment-management permission. Approved service-request context takes precedence; the shortcut cannot turn it into a payment document or bypass authorization.
- Receipt payment searches now provide readable no-match guidance. Existing Cash-only, accepted-payment, monthly 2026, zero-penalty and verified collector/property requirements are unchanged. Do not fabricate a payment or weaken eligibility to populate the dropdown; verify existing payment provenance through the authorized workflow when appropriate.
- `/certificates/payment-source/review` returns the exact uploaded PDF bytes, privately and administrator-only, without a watermark or font dependency. Populated previews remain watermarked; finalized PDFs are separate, non-interactive saved documents.
- The current source requires one-time authorized confirmation. An approval for the old PDF does not approve this distinct version. Old source records, DOCX assets, saved PDFs and historical download behavior remain intact. Stale previews cannot issue against a new source; payment/source uniqueness continues to protect retries.

## Source preservation and mapping

The unchanged current asset is `resources/certificates/resibo-butaw-pdf-2026/reference.pdf`, **190,211 bytes**, SHA-256:

`ae0f3fb8ca5470aeebb97c22eec429536e0e71a7648ef0cf8f91cc389361dc30`

The previous assets remain under `resibo-butaw-2026`; they are not a fallback when the current uploaded PDF is missing. `source_format: pdf` makes the current manifest validate the PDF itself, without requiring a DOCX or Word conversion. Earlier DOCX manifests remain supported for historical compatibility/testing, not silently selected for new issuance.

The original page remains **612 × 792 pt (Letter)** with a single slip at its original position. Mapping was measured from this PDF rather than reused from the ten-slip version. Inserted text uses the pinned original Calibri font at **11.04 pt**. Coordinates are in PDF points:

| Field | x | y | Width | Height |
| --- | ---: | ---: | ---: | ---: |
| Amount | 107.16 | 94.44 | 115.18 | 13 |
| Month | 86.52 | 107.88 | 137.02 | 13 |
| Block | 68.4 | 134.76 | 43.9 | 13 |
| Lot | 134.28 | 134.76 | 76.78 | 13 |
| Recorded collector | 75.359 | 161.52 | 142.531 | 13 |

The printed 2026, wording/capitalization, logo, borders and whitespace remain unchanged. No crop, resizing, document recreation, extra identifiers or fallback font is introduced. Unsupported text and overflow remain field-level failures, not reasons to shrink or truncate names.

## Verification and rollout

Initial file-only verification passed: one original Letter slip, original font/size, Unicode, watermark and no form fields. Source import differs by at most 1/255 raster rounding; populated-versus-blank changes were confined to mapped blank rectangles (2,190 changed color channels). This is pixel/layout verification, not byte identity for newly generated PDFs. The source-review response itself is required to be byte-identical.

Run `php scripts/preview-butaw-layout.php` and the existing private PyMuPDF environment's `scripts/check-butaw-layout.py` for repeatable engineering review. These scripts write only synthetic review artifacts; they neither access the ledger nor approve a source. Exact automated/browser/build results are appended after execution, including any failures/retries.

No new migration is required. No live source approval, live financial/account change, push, deployment or historical regeneration was performed. An authorized HOA reviewer must confirm the new PDF/mapping before official new receipts; engineering fixtures do not provide that approval. Deployment must rebuild/clear stale configuration through the reviewed release process if config caching is used. Physical printing, real HOA approval and independent host-security recovery remain external gates.

## Automated evidence

- Focused SQLite/document/UI tests: **21 passed, 162 assertions**, 21.68 seconds (`storage/logs/receipt-pdf-correction-focused-final.txt`). These cover exact review bytes without a font or approval side effect, staff denial, historical downloads, distinct-version approval, stale previews, overflow/unsupported text, empty/populated history, actual missing-table guidance, shortcut authorization and service-request precedence.
- Initial focused failures were test-harness issues: normalized Cache-Control ordering, array key order after model reload, modal-only schema assertions, and attempting to mock a final class. Corrected tests inspect mounted schemas and simulate a missing table transactionally on SQLite; this DDL simulation explicitly skips native MySQL rather than implicitly committing a fixture transaction.
- Production Vite build passed in **16.76 seconds**, with the existing runtime hero-image warning. Changed PHP/JavaScript syntax and diff checks passed. English/Filipino dictionaries have **1,090 matching keys**; human editorial approval remains pending.
- The first combined Chromium run passed the admin/staff compact-header checks and the new empty-history/exact-source case, but its generation case timed out at a five-second dialog assertion while the matching Livewire request was still pending. The generation harness now awaits that response explicitly with a bounded timeout, retaining every approval, permission, PDF and financial assertion. The retry and full-suite results are recorded below when complete; the initial combined run is not labelled an unconditional pass.
- Chromium generation retry **passed (1 case, 4.9 minutes)**, including actual isolated Cash recording, current-source confirmation, populated preview, final generation and matching saved View/Print/Download bytes (`receipt-pdf-correction-generation-browser.txt`). Together with the first three passing cases, this verifies **four distinct Chromium workflows**; it is not a clean four-case combined run. Responsive widths, menu focus and bounded serious/critical Axe checks passed on the exercised views. No Firefox/WebKit or native MySQL rerun is claimed for this correction.
- Final Pint, changed-file syntax/whitespace and repeated PDF checks passed. Both the Downloads original and preserved current source retain the expected checksum. The browser fixture needed its private framework cache directories created before initial migration; the first attempt stopped before schema creation and was retried only on the explicitly disposable SQLite file. No ordinary application/database cache or data was reset.
- Final full SQLite suite: **402 passed, five native-only skips, 4,178 assertions**, 632.14 seconds (`receipt-pdf-correction-full-sqlite.txt`). The five concurrency cases require native MySQL and were not treated as SQLite passes. No new regressions remain in this run. The audit-owned server on 8019 was stopped; synthetic fixtures/logs remain private for review. No real source approval or existing financial-record update occurred.
