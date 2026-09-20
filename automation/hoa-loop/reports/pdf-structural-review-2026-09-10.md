# PDF structural and visual pre-UAT review

Date: 2026-09-10  
Scope: Existing-output review plus temporary application-native generation of current MySQL UAT receipt, certificate, and report PDFs

## Existing certificate result

Reviewed `storage/app/private/certificates/2026/09/CERT-2026-0001.pdf` without modifying it.

| Check | Result |
| --- | --- |
| File size | 878,507 bytes |
| Encryption | Not encrypted; delivery protection is application authorization rather than PDF-password encryption |
| Pages | 1 |
| Page geometry | A4 portrait, 595.28 x 841.89 points |
| Text extraction | Complete association, address, type, resident, property, issue date, issuer, and certificate number |
| Visual renderer | PyMuPDF at 2x resolution, 1191 x 1684 pixels |
| Visual defects | None observed: no clipping, overlap, broken borders, black squares, missing glyphs, or unreadable text |
| Layout | Clear hierarchy, readable contrast, balanced margins, signature line, and certificate reference |

The sample text contains `Block 10 Lot 10 10 Notify Street` because its synthetic fixture independently uses Block 10, Lot 10, and house number 10. This is valid field composition and not a PDF rendering defect; representative human UAT should use realistic address values.

## Current MySQL candidate documents

The application generated temporary review copies from one synthetic paid obligation and one synthetic issued certificate in `hoa_system_uat`. The temporary PDFs and PNG renders were removed after inspection; the authorized persisted certificate and its database record remain available for witnessed UAT.

| Document | Geometry | Content verification | Visual result |
| --- | --- | --- | --- |
| Payment receipt | 1-page A4 portrait | OR `OR-20260910-TET1WF`, resident/property, dues, period, September 10 payment date, reviewer, and PHP 500.00 amount extracted | Pass: no clipping, overlap, missing glyphs, broken borders, or unreadable content |
| Certificate of Good Standing | 1-page A4 portrait | Resident/property, purpose, September 10 issue date, issuer, `CERT-2026-0001`, and September 10, 2027 expiry extracted | Pass: no clipping, overlap, missing glyphs, broken borders, or unreadable content |
| Payment Summary by Period | 1-page A4 landscape | Title/generator, September 2026 row, count 1, paid 500.00, balance 0.00, and September 10 latest date extracted | Pass after palette alignment: no clipping, overlap, missing glyphs, broken table lines, or unreadable content |

The report template was also rendered with 120 synthetic rows. It produced five A4-landscape pages, preserved all 120 row tokens, repeated the table heading and confidentiality footer once on every page, retained the first and last rows, and showed no row splitting or clipping on first/last-page visual review.

## Defects found and corrected

- Laravel's default UTC timezone initially produced September 9 generation/issue identifiers while the community's local date was September 10. `config/app.php` now defaults to `APP_TIMEZONE=Asia/Manila`; `.env.example`, local UAT configuration, deployment documentation, and a regression test were updated. The synthetic fixtures and PDFs were regenerated with internally consistent September 10 values.
- The report used an unrelated pink/purple palette. It now uses the same teal/navy/neutral document palette as receipts and certificates.
- Added explicit repeated table-header and row page-break safeguards for multi-page reports.

Focused configuration, certificate, dues, and report tests passed 35 tests / 127 assertions. Canonical `composer qa` passed Pint and 131 tests / 528 assertions after the fixes.

## Template review

- `certificate.blade.php` uses A4-safe millimetre margins, DejaVu Sans, controlled type sizes, and a single-column body suitable for long names and addresses.
- `payment-receipt.blade.php` uses A4-safe margins, a fixed two-column detail table, a distinct amount block, and a fixed generation footer.
- `report.blade.php` uses a fixed-layout full-width table, wrapping cells, compact typography, repeated semantic table headings, an empty state, filter metadata, and a confidentiality footer. The controller explicitly selects A4 landscape.
- Controllers and persisted-certificate delivery enforce policy/status/file checks before download; receipt/report authorization is covered by the automated test suite.

## Tooling limitation and remaining gate

The PDF skill's required artifact-operation marker (`container_tools/mark_artifact_operation_started.mjs`) is not present in this workspace, and Poppler is not installed. The marker command was attempted once and failed because the module is absent. Chromium headless also downloaded the PDF rather than rendering its viewer, so application-native temporary QA outputs were rendered with the already-installed PyMuPDF package as the documented fallback; none was delivered as a final PDF artifact.

The current MySQL-candidate receipt, certificate, and payment-summary PDF receive machine visual passes only. Each representative report type still requires its specified filter/download workflow, and all documents require witnessed authorization, opening/printing, content validation, and evidence attachment before DOC-01 through DOC-03 can be accepted.
