# Actual payer selection

## Findings and changes

The previous Create Payment form assigned the liable homeowner ID whenever dues changed, before checking the independently loaded eligible-payer list. The installment dialog also reused an earlier payer without rechecking eligibility. An excluded or stale selection could therefore appear as a raw ID and fail validation. These conditions were reproduced with isolated fixtures; the specific production screenshot was not diagnosed through resident records.

Both entry points now share `PaymentPayerSelect`, which delegates all eligibility to the existing `PropertyDuesAccounts` service. It presents a native, keyboard-accessible property-specific list with name and translated ownership type, avoiding independent asynchronous label loading. No account, property, landlord or payer policy was relaxed.

- Changing dues clears the prior payer and defaults only to the liable homeowner if currently eligible. Otherwise explicit selection is required; other eligible people are not automatically assumed to have paid.
- Before selecting dues, the payer field is disabled. Empty eligible lists explain that HOA review is required and cannot submit a payment.
- Validation clears an invalid/stale selection, retains the remaining fields, and attaches an actionable translated error to the payer control. Existing error feedback focuses that native control after dismissal. Existing locked backend checks still decide final eligibility; payer-specific failures occurring after initial form validation use the same feedback/clear path.
- Additional payments use the same defaults, options and stale-selection handling. Historical edit screens show only the original payer, including an inactive/archived payer, in a disabled non-submitted control. A legacy null payer displays its existing liable-account fallback without rewriting the record.
- English/Filipino guidance, review errors and ownership labels are included. Resident names are not translated. No migration, SMS/email setting, queue behavior, account status or business-record correction is included.

## Verification

- Combined SQLite workflow run: **190 passed, 1,095 assertions**, no failures, errors or skips. Covers payment management, tenant eligibility, installment/receipt protections, certificate dues checks and email/SMS reliability. JUnit: `storage/framework/testing/payer-selection-regressions.xml`.
- Disposable MariaDB: **7 passed, 43 assertions**, including payer relationships changed while waiting on the property lock, duplicate payment tokens, allocation races and settlement receipt issuance. Private evidence: `hoa-readiness-gaps-pFHf2j/report.json`; the temporary server was stopped by its runner.
- Tooling: **21 passed**; targeted Pint, JavaScript syntax and `git diff --check` passed. Production build passed with only the existing hero-image runtime-resolution warnings.
- Chromium browser rerun: **2 passed**, desktop 1280px and mobile 390px. Actual dues-dropdown interaction, named eligible defaults, retained amount, empty-list guidance, keyboard focus, installment cancellation/settlement/history and fresh-page Filipino guidance passed. English/light and Filipino/dark screenshots were visually inspected; private evidence: `hoa-alert-browser-interface-L3Iksj/payer-selection-*.png` and the corresponding installment screenshots. No PHP errors were recorded in the final run.
- An initial combined browser run passed mobile but desktop exceeded PHP's cold modal-view compilation limit while other regressions were running. The isolated runner now explicitly uses its private compiled-view directory and precompiles views before installment browser interactions; the final rerun passed. Production timeout/view settings were not changed.
- A final focused recheck of the shared field lookup and failure feedback passed **2 tests, 49 assertions**. Both backend-race error paths can resolve the shared component, and rejected installments do not show a success notification.

All fixtures are isolated; transports are fake/disabled. Automated notification assertions do not establish delivery.

Commands:

```powershell
$env:CERTIFICATE_BUTAW_FONT='C:/Windows/Fonts/calibri.ttf'
php vendor/phpunit/phpunit/phpunit tests/Feature/ButawReceiptTest.php tests/Feature/SharedPaymentReceiptsTest.php tests/Feature/DuesAndPaymentManagementTest.php tests/Feature/TenantPropertyPaymentsTest.php tests/Feature/CertificateDuesEligibilityTest.php tests/Feature/EmailReliabilityTest.php tests/Feature/SmsCommitBoundaryTest.php tests/Feature/SmsIntegrationTest.php tests/Unit/RecordPaymentTest.php --display-skipped --log-junit storage/framework/testing/payer-selection-regressions.xml
node scripts/run-readiness-gap-checks.mjs --payment-settlements
node scripts/run-interface-browser-checks.mjs --installments
npm.cmd run test:tooling
npm.cmd run build
```

Browser fixtures add one synthetic inactive liable account to exercise the empty-payer case. Checks cover actual dues-dropdown clicks, names instead of IDs, retained amounts, keyboard focus, empty lists, restored eligible defaults, installment cancellation/settlement/history, and fresh-page Filipino guidance at 1280px/390px. Existing shared locale hydration behavior remains unchanged; Filipino checks reload before opening server-rendered interfaces.

## Release boundary

Implemented and verified locally, then deployed after separate user authorization. `node scripts/package-payer-selection.mjs` prepares six application/translation files and the matching production build, excluding tests, credentials, fonts, migrations and private documents. The SSH release compared production differences, created private source/database backups, installed the new shared field before consumers and the manifest last, refreshed compiled views and verified checksums/read-only HTTP behavior. Production records, `.env`, `.htaccess`, settings, cron and queued/failed jobs were preserved. No migration or test message was needed. See `../deployments/payer-selection-2026-11-12.md` for deployment and rollback evidence.

Local archive: `C:/Users/bargo/AppData/Local/Temp/hoa-payer-selection-release-oK19E7/release.tar.gz`, **32 files**, SHA-256 `71a97fe8cf804bb65adb7386567d07b880aecfb289610121558744309702a57a`. The packaging-time manifest retains its original `deployed: false` marker; the later deployment record and private server verification establish installation.
