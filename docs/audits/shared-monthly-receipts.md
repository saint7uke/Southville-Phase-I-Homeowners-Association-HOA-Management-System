# Shared monthly receipt — Certificates and Payments (2026-10-21)

## Delivered behavior

Both modules now resolve the same latest finalized Resibo Butaw document for a payment, ordered by `created_at` and then `id`. The existing `/payments/{payment}/receipt` URL delegates to the same saved-document responder as `/certificates/payment-documents/{paymentDocument}`. Print uses `?view=1`; download uses attachment disposition. Filename, private path, checksum and PDF bytes are otherwise the same. No PDF is rendered or created by a monthly-receipt GET.

Payments offers **View / Print** and **Download** when the original PDF exists, or **Generate monthly receipt** when an eligible payment has no saved document. Generation opens the existing Certificates page with `?document=butaw&payment={id}`. The liable homeowner is derived from the payment server-side; a forged homeowner query value is never used. Invalid/ineligible payment hints show a field error without starting a preview. Approved Residency request context still takes precedence.

Certificates receipt history labels the latest file **Current receipt** and older immutable files **Historical version**, including their timestamps. Explicit old document links still serve their original files, not a regenerated current version. History and payment-table availability use the shared eligibility/resolution layer.

Unsupported accepted payments (other years/frequencies, penalties, historical electronic methods or insufficient verified provenance) keep the clearly labelled **Detailed receipt** and existing filename/layout. Missing schema, source setup or a required generation font is not used to silently replace an eligible original receipt with the HTML format. Missing/tampered saved files return readable 404/409 feedback; storage-read failures are reported with safe 503 feedback. Generation remains a separate confirmed workflow.

## Authorization and data integrity

Saved-file reads now use the existing payment-view policy: authorized managers, the liable resident and the actual payer retain access. Unrelated household members do not gain access just from sharing an address. Generation and watermarked drafts still require active administrator/staff payment-management authorization; original-source review/confirmation keeps its existing administrator settings requirements.

Downloads recheck account/payment state, eligibility and file integrity. Deleted/rejected payments cannot expose stored originals. Stored receipt reading does not require current source assets, font installation or the temporary preview table. New generation continues to require complete schema and authorized source confirmation.

No new migration, source/template redesign, numbering change, ledger adjustment or automatic approval is introduced. The preserved original PDF and its five mapped blanks are unchanged. Schema-ready checks are request-scoped; payment tables eager-load the latest document and required relationships. The regression test verifies zero additional per-row SQL queries when resolving eager-loaded receipt availability.

## Changed modules

- Payment model/resolver and service registration: deterministic latest-document relationship, availability, history query, compatible legacy responses and source/setup guidance.
- Payment and original-document download controllers: one saved-file responder with matching dispositions/bytes.
- Shared admin/staff and homeowner payment actions: original versus detailed receipt labels, generation shortcut and read-only pending/setup feedback.
- Certificate creation/history: trusted payment preselection and explicit current/historical version labels.
- English/Filipino copy, safe unavailable-receipt view, feature/browser tests and isolated test-only browser router/seeder.

## Verification log

- First new feature run: 12 passed; one synthetic fixture incorrectly attempted mass assignment of protected account status. The fixture now deliberately uses `forceFill`; production protection was preserved.
- Related regression then exposed an eager-loading callback expecting `Builder` where Eloquent supplies a `BelongsTo` relation. The callback now uses its underlying builder; no validation/authorization assertion was removed.
- Related document/payment regression: **95 passed, 580 assertions**, 404.32 seconds (`storage/logs/shared-receipts-focused-final.txt`). This preceded one additional stored-document/missing-preview-schema case; final full coverage is recorded below when complete.
- Initial Chromium attempts reached preview/responsive checks but failed to open confirmation. Trace inspection showed the server mounted the confirmation correctly; the test router failed to propagate Laravel's `return false` for static assets, returning HTML instead of Filament JavaScript. The router now propagates that return value, and the browser test checks JavaScript MIME type before proceeding. No product confirmation/authorization safeguard was removed (`shared-receipts-browser.txt`, `shared-receipts-browser-retry.txt`). These failed attempts are not acceptance evidence.
- The subsequent corrected-router run hit a 60-second PHP request timeout before preview completed (`shared-receipts-browser-assets-fixed.txt`). A separate retry uses opcode caching on the isolated test server; results are recorded below. This is not treated as a browser pass.
- With working assets, Axe identified a real 3.73:1 contrast failure on Preview/Generate controls (`shared-receipts-browser-opcache.txt`). Those controls and the receipt confirmation button now use the existing darker-blue design token, scoped to these actions. The original PDF is unaffected. A new Livewire regression mounts the actual schema action and confirms that no document exists until explicit confirmation.
- Production build passed with captured exit code 0, 112 seconds (`shared-receipts-build.txt`). The existing missing runtime hero-image warning remains unrelated. No dependency/tool upgrade was performed.
- Full SQLite regression: **427 passed, 5 skipped, 4,477 assertions**, 1,650.30 seconds (`shared-receipts-full-sqlite.txt`). The five skips are native-MySQL-only cases. This run preceded the final UI-only contrast correction and added confirmation test; a focused post-correction run is tracked separately.
- Native MySQL **8.4.9**: preflight passed; focused shared receipts, original receipts and property/receipt concurrency tests: **42 passed, 3 skipped, 355 assertions**, 805.06 seconds (`shared-receipts-native-preflight.txt`, `shared-receipts-native-mysql84.txt`). Three transactional schema-drop simulations intentionally run only on SQLite. This is focused native coverage, not a full native-MySQL acceptance run.
- PHP syntax passed for **365** application/feature/support PHP files; browser JavaScript syntax and scoped `git diff --check` passed. Pint passed on the 17 affected PHP files (`shared-receipts-pint-complete-final.txt`), then on the final UI/test additions (`shared-receipts-pint-last-ui.txt`). One resolver formatting failure was corrected before those final checks.
- Final Vite build after the contrast correction passed in **48.45 seconds** (`shared-receipts-build-final.txt`), with the same existing runtime hero-image warning.
- Original-background visual verification passed: Letter geometry, original font, non-interactive output and changes confined to the five mapped blanks. The preserved source SHA-256 remains `ae0f3fb8ca5470aeebb97c22eec429536e0e71a7648ef0cf8f91cc389361dc30`.
- Post-correction shared feature tests: **15 passed, 166 assertions**, 51.39 seconds (`shared-receipts-post-browser-regression.txt`). The new confirmation test also passed on native MySQL: **1 passed, 11 assertions**, 32.97 seconds (`shared-receipts-native-confirmation.txt`). Final PHP syntax/Pint checks passed for both changed PHP files.
- The contrast-corrected browser run passed the light/dark Axe checks but then exposed a test-locator defect: the Filament role wrapper has zero height while its fixed-position dialog window is visibly open in the screenshot. The test now asserts the actual window and keyboard focus containment, retaining confirmation and full byte-identity assertions (`shared-receipts-browser-contrast-fixed.txt`). No application modal behavior was weakened.
- Final Chromium workflow **passed** in **39.0 seconds** (`shared-receipts-browser-modal-window.txt`): Payments shortcut and trusted preselection, keyboard preview, original PDF preview, 320/768/1440 widths without horizontal page overflow, light/dark Axe, actual confirmation-window visibility/focus containment, modal Axe, explicit generation, matching saved bytes from both modules and the legacy URL, inline disposition, and Certificates history links. No serious/critical Axe findings remained on the tested creation page/dialog. Earlier failures/retries are retained in the log above, not treated as unconditional passes. Other browser engines and physical printing are not rerun in this increment.

## Isolation and rollout

Existing uncommitted changes and historical documents remain intact. All migration/workflow execution is against explicitly disposable databases and private test storage with synthetic users, array mail and intercepted/isolated notifications. The test-only router refuses non-testing/non-isolated configuration and initializes the private storage path before application bootstrap. HTTP logs are redirected to private files to avoid console backpressure.

No live migration, account provisioning, source approval, data repair, external notification, push or deployment is included. The owned disposable native MySQL listener (33187) and isolated browser listener (8023) were stopped after verification; no listener remains on either port. The user's normal application/database processes were not stopped. Disposable fixtures and private diagnostic logs remain available.

Gate states: **implemented and tested** for this shared-receipt increment within the coverage above; **awaiting human approval** for real HOA source/physical-print approval; **externally blocked** for independent host/credential incident recovery. This increment does not establish full-system/native-MySQL-suite/cross-engine acceptance or authorize production release.
