# Original Cohabitation source — authorized phase and signatory corrections

The user subsequently authorized changing only `Phase 1-A` and its following blank to
`Phase 1 - ____`. This supersedes the earlier instruction to await a corrected upload;
it does not authorize a redesign or automatic official activation. The user subsequently
confirmed the narrow punctuation correction `JOSEPHINE D, ALARZAR` to `JOSEPHINE D. ALARZAR`.
No other signatory text or signature image changes.

## Preserved and corrected files

- `original-unadjusted.docx`: unchanged upload `Cohabilitation Certificate.docx`.
  SHA-256 `9b572c18fc08ecc0edc909dced3069b3ead51f29c1be6beb6737cc0a3a0fe436`.
- `original.docx`: separate corrected copy. Two authorized literal text fragments change in
  `word/document.xml`; all 19 other ZIP package parts are identical.
  SHA-256 `44e225a6960a19e48249fe00a3d405b909c9c1a8ef57a9adfe266859e549284a`.
- `reference.pdf`: native Word export, opened read-only with macros/link updates disabled.
  One Letter page, 612 × 792 points.
  SHA-256 `0ea3553a9ee40357f1a39dafe138f1a55dd56cb8de8908d32a278f9ebfb79396`.
- `layout.json`: measured blanks and original Arial 12pt profile. The registered suffix fills
  both phase locations; this version has no separate address-continuation blank.
- `history/phase-only-20261021/`: previous corrected DOCX, Word PDF, map and README,
  preserved byte-for-byte. This is provenance, not a new management page or automatic approval.

All other wording, including `CERTIFICATE OF COHABILITATION`, printed 2026, logos and
`SHOAi PRESIDENT`, remains unchanged. The name now prints `JOSEPHINE D. ALARZAR`.
Issuance is restricted to 2026.

## Reproduction and technical verification

`scripts/correct-cohabitation-phase.php` applies both specifically authorized corrections,
is copy-only and refuses changed originals or an existing destination. An optional first
argument specifies a separate output DOCX. Do not rerun it or overwrite reviewed files on deployment.
Word exports may acquire new metadata/checksums when repeated; new bytes require a new source
review/version, not approval transfer.

`scripts/export-cohabitation-reference.vbs` uses a separately owned Word process without closing
the user's Word instance. Both exports succeeded. The unadjusted Word PDF for comparison is
private in `storage/framework/testing/cohabitation-phase-review`. The reference was visually
inspected. The signatory correction uses another isolated native Word export. Compared with
the archived phase-only PDF, only 576 color channels change, all in the printed signatory line;
all text elsewhere and the existing blank coordinates are unchanged. Compared with the initial
upload's Word export, changes remain inside the two authorized phase/signatory regions.

Run `php scripts/preview-cohabitation-layout.php`, then `python scripts/check-cohabitation-layout.py`
with PyMuPDF and the licensed Arial font. These produce synthetic, file-only review samples,
never official certificates or source approval. Populated changes must stay in mapped blanks.
Both scripts accept an optional first argument for a separate review-output directory. The
latest engineering evidence is `storage/framework/testing/cohabitation-signatory-review-20261021`.

## Approval and use

Certificates → Document setup should show **Ready for review** when database prerequisites
and the exact font are available on a database without this source's confirmation. An authorized
administrator confirms the document and printed signatory once for this version; bundled files
do not automatically approve a source on another database.
Any confirmation of the previous punctuation version does not approve the changed source.

On **2026-10-22**, the user explicitly designated existing administrator `admin@southville.test`
after confirming the corrected source/signatory. The configured local database now records
template **ID 4 / version 1** as **Approved**, actor **ID 1**, at **08:59:31 Asia/Manila**;
audit **ID 123**, `panel=system`, identifies the delegated CLI operation. A private pre-change
metadata backup and separate read-only verification are documented in
`docs/audits/original-cohabitation-certificate.md`. No interactive login, independent signed HOA
resolution or physical-print acceptance is claimed. No certificate was issued or historical PDF
regenerated. Refresh the certificate form and create a fresh preview before issuance.

The form derives the suffix read-only from the registered property. Unresolved phases must be
corrected in homeowner management; never guess them or alter financial/household identities here.
Historical snapshots, source versions and saved PDFs remain unchanged. No migration is required.
