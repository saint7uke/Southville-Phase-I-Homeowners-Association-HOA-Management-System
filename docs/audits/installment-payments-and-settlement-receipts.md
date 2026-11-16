# Installment payments and monthly settlement receipts

## Behavior

- One existing dues obligation remains the property/type/billing-period ledger. Additional payments append independent payment records; they never overwrite earlier amounts or historical statuses/balances.
- Admin and currently authorized Staff receive icon-only additional-payment and history actions. The dialog fixes the obligation, displays assessed/accepted/reserved/available amounts, and reuses the existing recording action, payer eligibility, integer-cent arithmetic and submission identity.
- Payment tables distinguish historical balance after an installment from the current obligation balance/status. A settled month cannot accept another payment; a different month remains payable.
- Pending proofs reserve funds but do not settle them. Existing electronic-proof restrictions are unchanged. No new notification producer was introduced.

## Monthly receipts

New Resibo Butaw previews/issuance require a Paid obligation, zero balance, reconciled accepted installment totals and no pending proof. Every contributing payment must satisfy existing 2026 Monthly/Cash/no-penalty/collector/property provenance restrictions. The printed amount is the accepted total; the collector comes from the payment that actually settled the obligation. This is identified by its recorded Paid/zero-balance outcome, not by the largest payment ID: an older pending proof can be approved last.

The additive nullable `payment_documents.settlement_obligation_id` unique foreign key provides one new settlement identity per obligation, independent of the installment entry point or source version. The existing payment link anchors to the settling payment. Snapshots retain each installment and collector; all contributors participate in preview freshness checks. Source approval, Calibri checksum, mapped blanks, overflow validation, private storage and PDF checksums remain enforced.

Issued settlement contributors cannot be financially changed, dated differently, archived or restored through supported operations. No reversal/reissue workflow is added. Notes remain editable because they do not affect the printed receipt or settlement snapshot.

Historical documents retain null settlement identity and original eligibility/download behavior. There is no automatic reconciliation, replacement, backfill or regeneration. Detailed transaction receipts remain available for installments/unsupported cases and now explicitly state that one payment does not certify monthly settlement. An existing payer's access to another payer's settlement document is not broadened; existing liable-account/final-payer/manager checks remain authoritative.

## Verification

- SQLite regression run: **131 passed, 822 assertions**, no skips. Includes receipt/security/history, payment management, tenant liability, certificate dues eligibility and email reliability. JUnit: `storage/framework/testing/installment-regressions.xml`.
- Additional SMS commit-boundary/integration and payment-calculation regressions: **51 passed, 161 assertions**, with synthetic data and fakes/disabled transports.
- Isolated MariaDB runner: **7 passed, 43 assertions**, no skips. Covers over-allocation, matching tokens, tenant relationship changes while waiting, and two installment-entry previews concurrently issuing one document. Evidence: private temporary `hoa-readiness-gaps-3Nxjfs/report.json`.
- Tooling: **21 passed**. Production build passed; existing hero-image path warnings remain build-time runtime-resolution warnings.
- Chromium desktop (1280px) and mobile (390px): **2 passed**, covering keyboard opening, 44px icon targets, cancellation, invalid zero amount, full settlement, action removal, history, viewport fit and Escape dismissal. English and fresh-page Filipino dialogs passed; dark-mode screenshots were visually inspected at both widths. Final synthetic evidence: private temporary `hoa-alert-browser-interface-zzjSkJ` (`installments-*.png`, `installments-fil-dark-*.png`).
- An earlier desktop browser attempt exceeded the local PHP 60-second cold-view compilation limit before the payment table loaded. The installment-only disposable server now allows 180 seconds; the final rerun passed with no PHP errors. This changes no application or production timeout setting.

Commands:

```powershell
$env:CERTIFICATE_BUTAW_FONT='C:/Windows/Fonts/calibri.ttf'
php vendor/phpunit/phpunit/phpunit tests/Feature/ButawReceiptTest.php tests/Feature/SharedPaymentReceiptsTest.php tests/Feature/DuesAndPaymentManagementTest.php tests/Feature/TenantPropertyPaymentsTest.php tests/Feature/CertificateDuesEligibilityTest.php tests/Feature/EmailReliabilityTest.php --display-skipped --log-junit storage/framework/testing/installment-regressions.xml
node scripts/run-readiness-gap-checks.mjs --payment-settlements
node scripts/run-interface-browser-checks.mjs --installments
php vendor/phpunit/phpunit/phpunit tests/Feature/SmsCommitBoundaryTest.php tests/Feature/SmsIntegrationTest.php tests/Unit/RecordPaymentTest.php --display-skipped
npm.cmd run test:tooling
npm.cmd run build
```

The MariaDB runner creates a uniquely named loopback-only disposable instance with destructive-test opt-in, then shuts it down. Two older concurrency classes now use the existing disposable truncation pattern instead of exercising unrelated historical down migrations during teardown; their race assertions remain intact. Initial teardown failures were not application payment failures.

No SMTP acceptance, inbox receipt, SMS gateway acceptance or handset delivery is claimed. Tests use isolated synthetic records and fakes/disabled transports. After separate authorization, production source/assets and the additive migration were deployed with private backups and read-only smoke verification; no production business workflow was exercised. See `../deployments/installment-payments-2026-11-12.md` for the deployment evidence and boundaries.

## Separate finding

The existing shared locale switcher translates explicitly marked client labels without remounting Livewire. Livewire retains its original hydrated request locale for newly rendered unmarked modal content until page reload. This is outside the financial change; language browser coverage checks server-rendered English/Filipino after a fresh page load. It does not certify instant translation of every already-mounted management interface. No shared locale behavior was changed here.
