# Export-only Cohabitation letterhead — 23 October 2026

## Implemented scope

The user's supplied `Cohabilitation Certificate.docx` contributes **only its upper
association logo and three association/address/registration lines**. The certificate
title, body, signature/officer details and lower artwork are not export headers.
No document-management screen or certificate source/version was added or approved.

| Entry point | New letterhead |
|---|---|
| Admin Reports direct PDF/XLSX | Yes |
| Newly generated queued Reports XLSX with saved `panel=admin` | Yes |
| Admin Households per-household PDF/new Excel action | Yes |
| CSV, JSON/Livewire previews, staff reports | No |
| Legacy payments-XLSX/audit-CSV endpoints | No |
| Shared `/households/{household}/summary.pdf`, including staff/homeowner links | No |
| Certificates, original-document previews, receipts, already-saved exports | Unchanged |

Scope is an explicit server-side opt-in, never a query-string branding toggle,
HTTP referrer or global PDF/spreadsheet event hook. Existing completed queue files
are returned unchanged; pending/new admin XLSX generation uses the new header.

## Source preservation and artwork

Microsoft Word exported the original read-only in an isolated owned process, with
macros/link updates disabled, through the existing guarded VBS exporter. It completed
successfully. The original checksum remained
`9b572c18fc08ecc0edc909dced3069b3ead51f29c1be6beb6737cc0a3a0fe436`.

The native one-page Letter PDF checksum is
`c8b59b219fa27c6a30d13250479998898ecb629e74edd259a54d61140bcac68c`.
Page-1 rectangle **(36, 28, 576, 128) points** was rasterized at **600 DPI** to
**4500 × 834 RGB pixels**, without retyping or substituting fonts. The resulting
PNG checksum is `d31dc1dcd91f3b1971178ff32a4fff9a199da4783157419fe9cf232d2821f73d`.
Its decoded pixels compare identically with a fresh rendering of that native crop.
The source logo's original resolution is not improved by the larger raster.

Private Word PDF and synthetic samples remain in
`storage/framework/testing/export-letterhead-20261023/`. Reviewed runtime artwork,
manifest and provenance are under `resources/exports/cohabitation-letterhead/`.
PyMuPDF 2.0 was installed into an isolated ignored testing-tool directory for
extraction/checks; no application dependency or package lockfile was added for it.
Production does not require Word, Python, PyMuPDF or original-document generation fonts.

## Interfaces and affected modules

- `AdminExportLetterhead` validates the bundled PNG checksum/dimensions and provides
  the same image to PDF and editable XLSX. Shared spreadsheet concerns default to
  no header. Report queries, row mapping, numeric values, properties, filters and
  chunked query execution remain authoritative and unchanged.
- `HoaReportController` opts in only on `admin.reports.download` for PDF/XLSX.
  `GenerateHoaReport` opts in only after authorization for saved admin XLSX records.
  Legacy payment/audit exporters do not opt in.
- New named route `admin.households.export`:
  `GET /admin/households/{household}/summary.{format}` (`pdf` or `xlsx` only).
  It requires the admin guard, active non-deleted administrator and
  `manage_homeowners`, and rejects archived households. Formats are validated in
  routing and controller; URL parameters do not change roles or permissions.
- Admin Households' PDF action uses the new route, with an additional **Provisional
  RBI Excel** action. Staff retain the old PDF route and have no new Excel action.
- Shared `HouseholdExportData` keeps active, non-archived member selection and
  manager-versus-homeowner field restrictions consistent. The new admin workbook
  contains name, relationship, residency status, optional birth date and sex, plus
  household identifier/address/count and the provisional RBI notice. No contact,
  payment, document or emergency data is newly disclosed.
- Admin household exports create an immutable `Household.exported` audit entry.
  Existing household/user membership, obligation, payment and certificate records
  are not modified by the exports.

The letterhead appears once above the content on the first PDF page and once at
the top of the worksheet. PDF orientations remain A4 landscape for reports and
A4 portrait for households. Table headings repeat on following pages. XLSX uses
reserved header rows, editable data starting below row 11's column headings,
repeated print-column headings, a header-inclusive print area and conservative
metadata heights. Household text uses literal string cells, not executable formulas.
Existing report formula neutralization remains in use.

Missing/corrupt artwork returns a localized **503 Export unavailable** page with
a return link, never a different logo or silent format fallback. Failed queued
generation retains its existing safe error/retry behavior and removes partial output.
New labels/error guidance have English and Filipino dictionary entries; the original
letterhead wording is deliberately not translated.

## Verification and evidence

| Check | Actual result |
|---|---|
| Reports/households/access/receipt regression | **70 passed / 536 assertions**, no skips/errors/failures; 120.26 seconds console time. Includes 16 new export tests. Evidence: `regression-tests.xml` in the private review directory. |
| Final export/certificate/original-document regression | **120 passed / 706 assertions**, no skips/errors/failures; 97.87 seconds console time. Includes all 16 new export cases after the long-metadata print-height refinement, plus original Residency, integrated issuance, Cohabitation and Butaw workflows. Evidence: `document-regressions.xml`. Counts overlap with the earlier selection; do not report their sum as unique tests. |
| Native artwork comparison | Identical decoded pixels at 600 DPI; original DOCX checksum unchanged. |
| Multi-page synthetic PDF checks | **3-page household / 85 rows**, **3-page report / 65 rows**; every row retained, artwork contained within paper, correct aspect ratio, no overlap, no repeated letterhead on later pages and no interactive fields. |
| XLSX structure | Actual generated workbooks loaded with PhpSpreadsheet; data parity, sorting/filtering, drawing count, literal formula-like names, print area, row repetition and queue scope verified. |
| PHP formatting / syntax | Full Pint check passed; all **14 changed/new PHP files** passed syntax; diff whitespace check passed. |
| Localization | **1,198 matching dictionary keys**, no parity errors; 41 pre-existing inventory candidates remain. |
| Production build | **Vite 7.3.7 passed, 22.41 seconds**; existing hero-image runtime-URL warning remains unrelated. |

Synthetic first/last pages were visually inspected; they do not constitute physical
printer or native Excel print acceptance. XLSX metadata rows reserve conservative
line heights for long addresses/filters instead of relying on merged-cell auto-fit.

Initial test evidence is retained rather than relabelled: a test-file parenthesis
was corrected before execution. The first 15-case run then reported three fixture
assertion failures (JSON float versus integer coordinates, spreadsheet-reloaded
string row indices, and exceeding the real five-per-minute export throttle).
Expected persisted types and synthetic test time were corrected; no production
throttle, permission or checksum assertion was disabled. An additional regression
command referenced a nonexistent test filename and stopped before execution; the
correct existing Residency suites were then selected.

Reproduction:

```powershell
php vendor/bin/phpunit tests/Feature/ExportLetterheadTest.php
php vendor/bin/pint --test
php scripts/audit-localization.php
npm.cmd run build
$env:PYTHONPATH = 'storage/framework/testing/letterhead-python-tools'
python scripts/check-export-letterhead.py
```

The last command needs optional isolated PyMuPDF and the retained private native Word
PDF plus synthetic test artifacts. Runtime/application tests do not need that tooling.

## Rollout and remaining boundaries

No migration, live-data repair, source/signatory approval, officer provisioning,
historical regeneration, dependency update, commit, push or deployment is included.
Deploy the reviewed artwork/manifest with the code and refresh normal route/config
caches through the established release process; no live cache changes were made here.
Existing uncommitted SC-01–SC-07 work remains preserved.

This increment does not claim a new full-suite/native-MySQL rerun, authenticated
cross-browser/Axe acceptance, native Excel rendering or physical print test. Those
remain explicit coverage limits. Independent **SEC-01 host/credential recovery** and
other documented human/operational release gates are not closed by these exports.
