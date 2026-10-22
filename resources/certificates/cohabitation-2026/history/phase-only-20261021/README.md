# Original Cohabitation source — authorized phase correction

The user subsequently authorized changing only `Phase 1-A` and its following blank to
`Phase 1 - ____`. This supersedes the earlier instruction to await a corrected upload;
it does not authorize a redesign, a different signatory or automatic official activation.

## Preserved and corrected files

- `original-unadjusted.docx`: unchanged upload `Cohabilitation Certificate.docx`.
  SHA-256 `9b572c18fc08ecc0edc909dced3069b3ead51f29c1be6beb6737cc0a3a0fe436`.
- `original.docx`: separate corrected copy. One literal text fragment changes in
  `word/document.xml`; all 19 other ZIP package parts are identical.
  SHA-256 `bb8b626f22c75950c4003ad6e8ac7e2ca4105a4b7146716de7031444da280e0b`.
- `reference.pdf`: native Word export, opened read-only with macros/link updates disabled.
  One Letter page, 612 × 792 points.
  SHA-256 `6cf0776aea29d1aaa03f49f81dbda0ad633ad872ed04d74ab9d9bf426aa7ee20`.
- `layout.json`: measured blanks and original Arial 12pt profile. The registered suffix fills
  both phase locations; this version has no separate address-continuation blank.

All other wording, including `CERTIFICATE OF COHABILITATION`, printed 2026, logos and
`JOSEPHINE D, ALARZAR / SHOAi PRESIDENT`, remains unchanged. Issuance is restricted to 2026.

## Reproduction and technical verification

`scripts/correct-cohabitation-phase.php` is copy-only and refuses changed originals or an
existing destination. Do not automatically rerun it or overwrite reviewed files on deployment.
Word exports may acquire new metadata/checksums when repeated; new bytes require a new source
review/version, not approval transfer.

`scripts/export-cohabitation-reference.vbs` uses a separately owned Word process without closing
the user's Word instance. Both exports succeeded. The unadjusted Word PDF for comparison is
private in `storage/framework/testing/cohabitation-phase-review`. The reference was visually
inspected; pixel comparison found zero changes outside the authorized phase-line region.

Run `php scripts/preview-cohabitation-layout.php`, then `python scripts/check-cohabitation-layout.py`
with PyMuPDF and the licensed Arial font. These produce synthetic, file-only review samples,
never official certificates or source approval. Populated changes must stay in mapped blanks.

## Approval and use

Certificates → Document setup should show **Ready for review** when database prerequisites
and the exact font are available. An authorized administrator must still confirm the document
and printed signatory once for this version. No real HOA approval is bundled here.

The form derives the suffix read-only from the registered property. Unresolved phases must be
corrected in homeowner management; never guess them or alter financial/household identities here.
Historical snapshots, source versions and saved PDFs remain unchanged. No migration is required.
