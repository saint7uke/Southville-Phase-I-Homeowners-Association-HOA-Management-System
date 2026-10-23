# Export-only association letterhead

Source: the user's `Cohabilitation Certificate.docx`, SHA-256
`9b572c18fc08ecc0edc909dced3069b3ead51f29c1be6beb6737cc0a3a0fe436`.
The original remains unchanged. This asset is not a certificate template, approval,
signatory confirmation, application logo replacement or a new document-management page.

On 23 October 2026, Microsoft Word opened that file read-only in an isolated process,
with macros and automatic link updates disabled, using the existing
`scripts/export-cohabitation-reference.vbs`. The resulting one-page Letter PDF is
retained privately at `storage/framework/testing/export-letterhead-20261023/word-reference.pdf`.
Its checksum and extraction details are recorded in `manifest.json`.

`scripts/extract-export-letterhead.py` extracts only page 1's rectangle
**(36, 28, 576, 128) points**, at **600 DPI**, producing **4500 × 834** RGB pixels.
The upper HOA logo and the three original association/address/registration lines
are included. The certificate title, body, signatures and footer artwork are excluded.
No lettering was retyped, no fonts substituted and no source artwork redesigned.
Preserve aspect ratio when displaying; source logo resolution is not artificially improved.

PyMuPDF 2.0 was isolated under ignored testing storage for extraction/review only.
Neither Python, Word nor PyMuPDF is a production/runtime dependency. Re-extraction
refuses to overwrite existing assets; new Word PDF metadata may change its checksum.
Any replacement requires deliberate source/provenance review rather than blind regeneration.

The PNG is explicitly enabled only for admin Reports PDF/XLSX (including newly queued
admin XLSX) and dedicated admin Household PDF/XLSX. Shared exporters default to no
letterhead; CSV, staff/homeowner summaries, legacy payment/audit exports, certificate
issuance, receipts and existing saved documents are outside this branding change.
