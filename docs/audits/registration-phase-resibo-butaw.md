# Registration, confirmed property identity and original Resibo Butaw

**Source superseded on 2026-10-21:** the new user-supplied single-slip PDF is now the source for new receipt generation. See [the PDF-source correction](receipt-pdf-source-correction.md). The ten-slip Word export, hashes and results below document the earlier version and are retained for history; they are not the current source or approval evidence for the new PDF.

## Scope and release state

This increment repairs registration readability, removes House Number from new applications, adds a required Phase suffix, and integrates original-document monthly receipts inside `/admin/certificates`. Existing uncommitted revisions were preserved. No live migration, actual HOA source approval, account provisioning, push, deployment or historical PDF regeneration was performed.

Technical implementation is separate from approval. Review backups, the read-only identity report and pending migrations before populated-database rollout. Actual HOA approval and the existing independent host/credential recovery gate remain outstanding.

## Registration and identity

- Normal labels inherit the design-system ink color; only required markers are red. Password icon styling targets only decorative icons. Requirement text can wrap at mobile widths. Neutral/success/error feedback retains live regions and non-color cues.
- The white registration card now explicitly establishes its foreground color, including the language control and Phase helper. This was verified after Axe exposed inherited light landing-page text on white.
- House Number is absent from registration markup, request payloads, availability checks and persistence. Non-empty forged values, including arrays, are rejected. Existing house numbers/columns remain; new records use null. Profile and household validation accept missing house numbers without erasing populated legacy values.
- `phase_suffix` accepts 1–10 ASCII letters/digits, is stored uppercase, and is displayed with `Phase 1 -`. The base `phase` remains separate. `PropertyIdentity` centralizes formatting and identity: normalized base phase + suffix + block + lot. Street and historical house numbers are address information, not confirmed identity.
- Indexed nullable `property_key` columns coexist with unchanged legacy `address_key` values. Unknown legacy phases are never guessed or merged. Current unresolved accounts at the same base/block/lot block new claims and landlord linking with an HOA-review message.
- Registration, landlords, household membership/privacy, payer selection and future dues generation share identity resolution. Owner restrictions and permitted Tenant/Co-owner sharing remain. Personal profile saves retain the registered suffix server-side; forged changes are rejected.
- Admin/staff `Confirm property phase` is transactional and audited. Linked tenants, multiple liable legacy accounts, conflicting households/current assessments require review. No automatic household merge or tenant reassignment occurs.
- Moving an already billed liable account to a different property during its current assessment period requires HOA assessment review. Personal edits and cosmetic street corrections remain allowed. This prevents current assessments from silently following a moved account and permitting duplicate property billing. The generator also locks/rechecks candidates so a concurrent address/status change cannot use its stale initial candidate list.
- An unresolved assessment belonging to an archived account also requires review before confirming a competing identity or creating a second assessment for that billing month/schedule. Registration itself remains available after archival; no historical assessment is reassigned or rewritten. Confirmation, registration, generation and the legacy payment-generation path share the ordered base/property mutex. The conservative legacy-assessment check locks the relevant period/schedule's unresolved rows; measure its cost on large legacy datasets before rollout.
- Address/landlord requests retain their two-field privacy-preserving JSON response shapes, CSRF and 30/minute limits, abortable 500 ms debounce, normalized cache and stale-response checks. Email checking additionally cancels blur/submission debounce races and ignores stale responses.

### Read-only rollout report

`php artisan hoa:review-property-identities --json` works both before and after the identity migration. It reports unknown suffixes, multiple Owners, household conflicts and current-period assessment conflicts. Results are review candidates, not proof that legitimate shared/legacy accounts are duplicates. It never updates accounts or the ledger.

The report includes unconfirmed current-period assessments even when their liable homeowner is archived. Resolve these review candidates manually before relying on confirmed financial grouping; never infer or bulk-fill their Phase suffixes.

Run against a reviewed backup/disposable clone first. Review ambiguous shared legacy households explicitly; this change does not authorize automatic legacy data repair.

## Authoritative receipt assets and conversion

The new DOCX supersedes the earlier PDF reference. The unchanged preservation copy is `resources/certificates/resibo-butaw-2026/original.docx` (69,678 bytes).

| Asset | SHA-256 |
| --- | --- |
| Uploaded DOCX / preservation copy | `a290ec1c2cbf7c17f403461ea94577205ee541be35ebe213eccc8ccb14cb04d9` |
| Pinned Word-exported PDF | `feed735518798f36a9b195aef1695028a2fc903eb2841b720fb08b9ca39343f9` |
| Exact installed Calibri font used for acceptance | `57e93ff143572bcc092c1f56202d43ddc07bb119aa104c6d7af163470fb551f6` |

PowerShell Office calls opened the read-only document but stalled during PDF/XPS export or saving. Save As PDF and a verified local virtual-PDF path were also investigated; the system default printer was not changed and no physical print was requested. No LibreOffice/Pandoc installation was available. The successful alternative was **Microsoft Word's native Windows Script Host COM call**, not HTML/PHPWord reconstruction or an approximate converter. The internal cause of the earlier PowerShell stalls is not established.

Reproducible export (refuses existing output):

```powershell
cscript.exe //nologo scripts/export-original-document.vbs resources/certificates/resibo-butaw-2026/original.docx storage/framework/testing/resibo-native-com.pdf
```

Word runs hidden, macros disabled, with the preservation copy opened read-only. Do not terminate another user's Word session. `export-original-receipt.ps1` preserves stable source bytes/checksums, but its PowerShell export variants stalled locally; the native script is the verified export path.

The actual export is **one Letter page, 612 × 792 pt, ten visible slips**. Coordinates were measured from this new export, not inferred from duplicated DOCX text boxes or copied from the earlier PDF. `layout.json` maps only the top-left slip's amount, month, block, lot and Paid-to blanks, using original **Calibri 11.04 pt**, with a pinned font checksum. The printed 2026 and all other wording, images, borders and nine remaining slips stay unchanged. No proprietary font file is distributed.

File-only checks:

```powershell
php scripts/preview-butaw-layout.php
storage/framework/testing/residency-pdf-tools/Scripts/python.exe scripts/check-butaw-layout.py
```

The engineering check passed: ten original slips, Letter geometry, Unicode, exact Calibri/size, draft watermark and no form widgets. Blank import versus source has at most 1/255 raster rounding; populated versus blank has **zero changes outside the first slip's mapped blanks**. This is design preservation, not a claim that a new PDF container is byte-identical to the source. Human Word/PDF and HOA print review remain required before actual approval.

## Certificates-page workflow and authorization

1. An active administrator with both `manage_settings` and `manage_payments` reviews and explicitly confirms the original receipt source, with evidence note, from Certificates. No separate template-management page or new certificate-catalog/service-request type exists.
2. In Create Certificate, select document **Monthly Dues Receipt (Resibo Butaw)**. Certificate-only fields hide; a searchable accepted-payment selector appears.
3. Active admin/staff require `manage_payments`, independent of certificate issuance permission. Certificate-only access cannot search or generate payment documents; payment-only access cannot enumerate certificate residents/records.
4. Eligible payments are current accepted Cash (`Recorded`/`Approved`, `Paid`/`Partial`), tied to monthly dues billed in 2026, with verified property/recorded collector metadata. Penalties on either the payment or obligation exclude this narrow receipt because a dues-only allocation cannot safely be inferred. Other years/electronic/penalty records retain the unchanged detailed receipt.
5. Amount is the selected payment's actual amount in exact cents; month comes from the obligation; block/lot and collector come from the payment snapshot. The generator is not substituted for the recorded collector. No arbitrary collector textbox exists.
6. Preview is private, watermarked, actor-bound, stale-checked and expires after 15 minutes. It creates no official number/payment/assessment. Explicit generation revalidates the source, font, permission and payment under locks, saves an immutable snapshot/PDF and is unique per payment/pinned source.
7. View/Print and Download serve the same saved bytes. Deleted, rejected, reversed or otherwise ineligible payments lose official access. No regeneration of existing documents occurs.

New Cash records snapshot property and collector data server-side. Legacy records without verified metadata are not automatically backfilled. An administrator can explicitly attest existing property/recorded collector evidence from the payment edit workflow; this is audited, cannot replace a verified snapshot, and changes no amounts or ledger allocation. Unverifiable records continue using the detailed receipt.

## Migrations and compatibility

- `2026_10_20_000006_add_confirmed_property_identity`: nullable house numbers and nullable indexed Phase identity on homeowners/households; no guessed suffix backfill. Populated SQLite nullability rebuilds run outside the migrator's implicit transaction, with FK enforcement disabled before a controlled transaction and checked before commit. MySQL follows native ALTER behavior.
- `2026_10_20_000007_add_original_payment_documents`: nullable payment snapshot plus immutable source/document and expiring preview metadata. No financial values or historical receipt files are rewritten.
- Related confirmed rollback repair: the older dues/payment migration now drops the `normalized_name` index before dropping that column, fixing populated SQLite rollback testing.

Do not use `migrate:fresh` on existing data. Back up the database and private files, review the dry-run report on a clone, test migration/rollback there, then obtain explicit rollout authorization. Prefer forward fixes after issuing documents; dropping new metadata on rollback would lose provenance. House-number nullability is intentionally not blindly reversed to NOT NULL. Updated Residency field mapping adds the confirmed suffix to the original suffix blank; its new internal version must be reviewed/confirmed under Certificates. Existing issued PDFs remain untouched.

## Verification evidence

Latest completed SQLite result: **389 passed, 5 native-only skips, 4,031 assertions**, 409.67 s (`registration-butaw-full-sqlite-complete.txt`). The final archived-assessment scenario is included. Browser evidence covers **18 distinct passing cases**, six per engine: 17 completed in the interrupted combined run, and its final WebKit receipt case passed separately in 2.8 minutes (`registration-butaw-browser-webkit-final.txt`). This is not represented as one uninterrupted 18-case run. Light/dark media, three widths, serious/critical Axe checks on exercised registration/receipt views, unsaved locale state, real registration and accepted Cash receipt generation were checked.

Final Pint check, targeted JavaScript syntax and diff checks passed. The repeat file-only document check again confirmed one Letter page, ten slips, exact original Calibri 11.04, Unicode, watermark/no interactive fields and no changed pixels outside the designated first-slip blanks. The original Downloads DOCX hash was rechecked after conversion and still matches the preserved source. Composer validation passed; a timed-out advisory request was retried successfully with no advisories. Production npm audit reported zero vulnerabilities. See the revision checklist for native retry/build completion and remaining gates.

Final focused native MySQL **8.4.9** retry: **61 passed, 379 assertions, no skips**, 344.48 s (`registration-butaw-native-complete-retry.txt`), including the five native-only race cases skipped by SQLite. This is the final focused selection, not a full native-suite rerun. Final production build passed in 35.57 s with the previously documented hero-image warning; all 435 checked PHP files passed syntax, and English/Filipino key parity is 1,076/1,076. Actual HOA approval and trusted-host release acceptance remain pending despite these test results.

- Full SQLite checkpoint: **385 passed, 5 MySQL-only skips, 4,005 assertions**, 275.56 s; `storage/logs/registration-butaw-full-sqlite-final.txt`. Later guard/rollback/field scenarios receive a final regression run recorded in the revision checklist; this checkpoint is not represented as including tests added afterward.
- Native **MySQL 8.4.9** checkpoint: **90 passed, 560 assertions**, 293.53 s; `storage/logs/registration-butaw-native-final.txt`. Separately named `hoa_registration_test_20261020`, standalone loopback port 33187; no MariaDB result is substituted. Successful races cover Owner claims, payment allocation/idempotency, original certificate issuance and receipt uniqueness.
- Focused latest regression checkpoint: **66 passed, 420 assertions**, 66.90 s; `registration-butaw-regression-latest.txt` (before the last billed-move test).
- Browser checkpoints and final counts are recorded in the revision checklist. Initial Axe failures were repaired, not waived. Source mapping is also independently checked by `check-butaw-layout.py`.
- PHP syntax checkpoint: **435 files, zero failures**. Composer validation/audit and production npm audit passed; production build passed with the existing runtime hero-image build warning. Final formatting/build results follow in the checklist.

No test or synthetic source approval constitutes actual HOA approval, broad PDF-accessibility certification, host-security recovery, or verification of every production dataset. Unresolved legacy identities/liability transfers require human review; Filipino terminology remains subject to editorial approval.

## Changed-module index for this increment

| Area | Principal files |
| --- | --- |
| Registration presentation and live checks | `resources/views/portal/auth/register.blade.php`, `resources/js/app.js`, `resources/js/locale.js`, `lang/en.json`, `lang/fil.json` |
| Registration validation/persistence | `RegisterRequest`, address/landlord availability requests/controllers, `RegisterHomeowner`, `PropertyAddress`, `PropertyIdentity`, `PropertyAddressAvailability`, `LandlordMatcher` |
| Resident/household compatibility | `Homeowner`, `Household`, `HomeownerProfileValidator`, `MyProfile`, profile browser validation, `HouseholdDirectory`, `ManageHousehold`, `OwnHouseholdSummary`, `HouseholdPdfController`, `ConfirmPropertyPhase`, homeowner forms/edit page |
| Dues and payments | `PropertyDuesAccounts`, `GenerateSettingObligations`, `RecordPayment`, `PaymentValidator`, `UpdatePaymentMetadata`, `ConfirmReceiptDetails`, payment edit page, `ReviewPropertyIdentities` |
| Original receipt and saved documents | `ButawReceiptSource`, `ButawReceiptEligibility`, `ButawReceipts`, `PaymentDocument*` models, original-PDF/font infrastructure, certificate resource/form/create/list pages, preview view, private routes/controller, audit registration/pruning, `config/certificates.php` |
| Original assets/tooling | `resources/certificates/resibo-butaw-2026/*`, `export-original-document.vbs`, `export-original-receipt.ps1`, `preview-butaw-layout.php`, `check-butaw-layout.py`; existing Residency suffix mapping/snapshots |
| Schema and tests | Identity/document migrations `000006`/`000007`, older indexed-column rollback repair, focused identity/migration/receipt/MySQL concurrency suites, updated registration/profile fixtures and `registration-phase-receipt.spec.js` |

This index distinguishes the current increment from the larger pre-existing uncommitted Phase 1–3 worktree; it is not a claim that every dirty file originated here.
