# Payment dues availability — 13 November 2026

## Scope

Read-only availability presentation using the existing `PropertyDuesAccounts::eligiblePayers` policy. No eligibility, account, property, assessment, installment or receipt policy is changed.

Open obligations remain discoverable. A disabled option explains either “Archived homeowner—HOA review required” or “No eligible payer—HOA review required”. An archived liable resident does not disable an obligation if an eligible co-owner or tenant exists. Active portal access does not override the resident archive flag.

Admin review links use existing homeowner view permissions and routes. Staff receive guidance without new review/restore authority. No restoration, activation or reassignment occurs. Invalid payer state is cleared while amount, date and notes remain intact; existing locked backend checks are unchanged.

## Verification

Focused additions to `TenantPropertyPaymentsTest`:

- `test_archived_liable_with_active_portal_is_disabled_but_not_restored`
- `test_archived_liable_does_not_disable_an_obligation_with_an_eligible_co_owner_or_tenant`
- `test_review_link_is_admin_only_and_availability_reasons_are_translated`
- `test_obligation_becoming_unavailable_after_opening_retains_input_and_clears_payer`

These and the existing no-eligible-payer test passed locally. Assertions cover disabled options, forged selections, no payment/audit/notification writes, preserved archived state, eligible payment recording, restricted review links and Filipino messages.

Commands:

```powershell
$env:CERTIFICATE_BUTAW_FONT='C:/Windows/Fonts/calibri.ttf'
php vendor/phpunit/phpunit/phpunit tests/Feature/ButawReceiptTest.php tests/Feature/SharedPaymentReceiptsTest.php tests/Feature/DuesAndPaymentManagementTest.php tests/Feature/TenantPropertyPaymentsTest.php tests/Feature/CertificateDuesEligibilityTest.php tests/Feature/EmailReliabilityTest.php tests/Feature/SmsCommitBoundaryTest.php tests/Feature/SmsIntegrationTest.php tests/Unit/RecordPaymentTest.php --display-skipped --log-junit storage/framework/testing/dues-availability-regressions.xml
node scripts/run-readiness-gap-checks.mjs --payment-settlements
node scripts/run-interface-browser-checks.mjs --installments
node scripts/run-interface-browser-checks.mjs --dues-availability
npm.cmd run test:tooling
npm.cmd run build
```

SQLite regressions: **193 passed, 1,130 assertions**, no skips. The additional stale-obligation case passed separately: **1 test, 16 assertions** (`dues-availability-stale.xml`). Disposable MariaDB: **7 passed, 43 assertions**, including allocation/token/receipt races and tenant relationships changed while waiting on locks; evidence `hoa-readiness-gaps-oglBY8/report.json`. Its server was stopped by the guarded runner.

Tooling: **21 passed**. Targeted Pint, PHP/JavaScript syntax and `git diff --check` passed. Production asset build passed with the existing runtime hero-image URL warnings. Synthetic tests/fakes do not establish provider acceptance or handset/inbox delivery. No live messages or production payment submissions are authorized for verification.

Browser status: initial extended runs failed on a local `ERR_NETWORK_CHANGED`, Windows PHP cold-request timeouts and interrupted modal requests. These are not counted as passes. Focused runs confirmed English disabled options, eligible selection and retained input, but the first new test used an insufficient default five-second locale wait and did not reset the shared fixture's persisted locale between widths. Corrected the test to use the existing suite's 60-second assertions and explicitly manage fixture locale. The guarded temporary router now applies the payment harness's timeout per request; no production timeout is changed. Final rerun results follow below when complete.

Final focused Chromium checks: **2 passed**, 1280px and 390px, with keyboard opening/focus, disabled reasons, eligible selection, retained amount, no horizontal overflow and Filipino/dark-mode rendering. Screenshots inspected under `hoa-alert-browser-interface-pxsmJW/availability-*.png`. Traces established that repeated OS network-change notifications aborted module loads (including the locale module); the isolated browser tests now forward each same-origin request exactly once through Playwright's HTTP client to the real disposable Laravel server. External traffic is blocked, redirects are preserved, and POST retries are disabled. Server validation, authentication, CSRF and browser interaction remain exercised; responses are not mocked.

An additional extended installment-browser rerun reached cancellation, invalid-amount handling and the final payment submission, but its desktop success-feedback assertion timed out while the submit control was still waiting. This is **not a pass**; no runtime cause has been established, and no unrelated notification/payment policy was changed to bypass it. Artifacts: `hoa-alert-browser-interface-iiS9i3/results`. PHP installment/receipt regressions and the focused availability browser tests passed independently.

The same extended run **passed the full 390px mobile flow**, including cancellation, invalid amount, settlement, unchanged earlier Partial history, icon touch targets, dismissal and Filipino/dark-mode history.

The separate desktop rerun (`--installments --grep 1280px`, artifacts `hoa-alert-browser-interface-Qrom3J`) passed availability, cancellation, invalid amount, successful settlement feedback and history assertions. It subsequently timed out waiting for the shared language switch to change `html[lang]` from English to Filipino. This extended test therefore **remains failed**, not fully green. Its English settlement/history screenshot was inspected. New availability copy and layout in both languages passed the independent desktop/mobile focused tests. The broader language-switch timing issue is recorded separately; no shared locale behavior or notification policy was changed in this release. All temporary browser servers were stopped by their runners.

## Release boundary

Five runtime files: `PaymentDuesAvailability.php`, `PaymentForm.php`, `PaymentPayerSelect.php`, English and Filipino JSON translations, plus matching build assets. No migration, configuration or historical-record cleanup. Deployed after user authorization with private backups and checksum/read-only verification; see [deployment and rollback](../deployments/dues-availability-2026-11-13.md). The archived homeowner remains subject to HOA review, not automatic restoration.
