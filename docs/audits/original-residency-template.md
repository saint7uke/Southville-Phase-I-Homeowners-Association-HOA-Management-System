# Original-layout Residency certificate
# Uploaded Residency document: integrated certificate workflow

## Source and preservation

The only Residency source is the user-supplied `2026-SHOAI-certificate (1).docx`, SHA-256 `b436ba82870db95049480cec8c379a33377244c1f7ab63c50ce04f62eb62479d`. It was exported read-only with macros disabled through Microsoft Word. The original file is unchanged. The pinned PDF in `resources/certificates/residency-2026` is the original document's one-page Letter rendering, not a redesigned HTML certificate.

The reference preserves the original wording, officer sidebar, logos, signature areas, printed signatory, printed 2026 year and small printed “5”. Word's alternate text-box representations export as one visible certificate. Name, block, lot, purpose, issuance day and English month are filled. The subsequent approved registration/Phase revision also maps the existing suffix blank to the resident's confirmed `phase_suffix`; unknown legacy suffixes remain blank. This supersedes the earlier blanket instruction to leave every suffix blank. No extra house/street/number/expiry field is drawn; certificate numbers and expiry remain system metadata.

See [Registration, Phase and Resibo Butaw](registration-phase-resibo-butaw.md) for the new metadata/migration requirements and the separate payment-document option within Certificates. No separate template-management page is restored. The unchanged Residency background retains its checksum; the updated seven-field mapping requires an explicitly reviewed internal version before new issuance. Existing PDFs are not regenerated.

## One workflow: /admin/certificates

The separate `/admin/certificate-templates` page and navigation entry are removed; its old URL returns 404. There is no Residency wording editor or replacement-template authoring workflow. Existing source/version rows are retained internally for provenance, relationships and historical compatibility.

1. An administrator with `manage_settings` opens **Document setup → Review uploaded Residency document** from the certificate list and compares the reference with Word.
2. **Document setup → Confirm official Residency source** requires visual-review confirmation, explicit HOA authorization of the printed `JOSEPHINE ALARZAR / SHOAi PRESIDENT`, and an evidence note. This is one-time setup per document version and disappears once that source/signatory is confirmed; it is not required for each homeowner. Neither this implementation nor the document's names establish signing authority.
3. Select a homeowner under **Create certificate**, choose Residency and enter an optional purpose. Registered name/address values are read-only record data, not arbitrary printed overrides.
4. **Preview completed certificate** produces a private watermarked PDF in the form, with an open-in-new-tab fallback. It allocates no official number, issued record or notification.
5. **Confirm issuance** rechecks permissions, resident/source/signatory/font/date and input. A changed or expired preview requires regeneration. The separate official PDF is finalized and immutable.
6. The list provides **View / Print** (inline PDF) and **Download**, serving the same saved bytes without regenerating history.

Staff retain their existing issuance permission but cannot configure the official source. Approved Residency service requests link to the same certificate creation/preview workflow, retaining the request association and completing the request only after successful issuance. Existing Cohabitation source review/approval is available as an authorized action inside the certificate list; its approval gate is not bypassed. Historical PDFs and retired-request compatibility remain unchanged.

## Exact field font and validation

Populated text uses **Times New Roman Bold at 12pt**, matching the source's blank-field text style. There is no FreeSerif substitution, shrinking, truncation or reflow. Unsupported glyphs, control characters, missing registered name/block/lot and values that exceed the mapped blanks produce readable field errors.

The deployment must supply an appropriately licensed Times New Roman Bold TTF. Windows defaults to `C:/Windows/Fonts/timesbd.ttf`; other hosts must configure `CERTIFICATE_RESIDENCY_FONT` through the approved deployment process. This work does not change `.env` or distribute the proprietary font. TCPDF conversions are cached privately under the font checksum with a conversion lock. A missing or substitute font blocks generation. The font checksum is bound into previews and issued snapshots.

Font-dependent acceptance tests explicitly skip when the licensed font is unavailable rather than using a substitute. The document browser case requires `HOA_BROWSER_RESIDENCY_FONT_AVAILABLE=true` against the isolated server. A Linux/CI skip does not prove original-font rendering; retain actual licensed-font acceptance evidence from a trusted deployment-equivalent runner.

The printed year is unchanged: this source cannot officially issue outside 2026. A different year requires a separately supplied, reviewed original document, not editing an approved source.

## Integrity, privacy and prerequisites

- The additive `2026_10_20_000005_add_certificate_previews` migration adds hashed, actor-bound preview records and a nullable unique `certificates.issuance_key`. It does not rewrite historical documents or approve sources. The earlier renderer/layout migration is still required.
- Preview tokens expire after 15 minutes. Confirmation locks their rows; matching retries return the same certificate. Source/resident/date/purpose changes invalidate unissued previews. Permission revocation is checked on reading and confirmation.
- Unique confirmation keys and existing request/sequence locks prevent duplicate issuance. Failed storage/database steps roll back records and remove newly written output.
- Preview records expired for more than one day are scheduled for pruning. No issued certificate or historical PDF is pruned by this job. The job waits until its migration exists.
- Reference, source, preview and issued endpoints enforce their respective authorization. Private responses use no-store headers. No interactive form fields, external links or executable template content are imported into official PDFs.
- Missing schema/source/font/signatory configuration returns readable setup errors; new Residency issuance never falls back to the old HTML design.
- Shared private background objects are content-addressed and checksum-verified. They are not deleted merely because one registration attempt fails.

Before deployment, obtain a reviewed database/private-storage backup, apply pending additive migrations through the approved procedure, supply the licensed font, and refresh route/view caches as appropriate to the deployment. No live migrations, real source confirmation, signatory approval, accounts, notifications, push or deployment are performed automatically. Rollback must preserve preview/source provenance, immutable snapshots and historical files; never use `migrate:fresh` against existing records.

## Engineering verification

`php scripts/preview-residency-layout.php` creates synthetic file-only review artifacts under ignored testing storage. `scripts/check-residency-layout.py` uses optional isolated PyMuPDF tooling, not a production dependency.

The blank PDF import preserves extracted text and Letter geometry. Reference/import raster differences were at most 2/255 color levels from transparency/antialias processing: this is not byte/pixel identity. Populating the document changes nothing outside mapped blanks (2pt antialias allowance). The check also verifies Unicode, Times New Roman Bold at 12pt, the draft watermark and no interactive widgets. Human Word/PDF comparison and viewer/printer acceptance remain approval tasks.

Exact automated/browser/native-database results are recorded in `shoai-revision-progress.md`. The independent host/credential incident and other external HOA release gates remain open.
## Source and status

The reference is `2026-SHOAI-certificate (1).docx`, supplied by the user. Source SHA-256: `b436ba82870db95049480cec8c379a33377244c1f7ab63c50ce04f62eb62479d`. It was opened read-only with macros disabled and exported using the locally installed Microsoft Word, not HTML/LibreOffice. The original DOCX hash was unchanged. No officers were provisioned, signatures reproduced, live templates activated or HOA approvals recorded.

The bundled `resources/certificates/residency-2026/reference.pdf` is one Letter page (612 × 792 points); its SHA-256 is pinned in `layout.json`. Original logos, sidebar, wording, fixed year, printed signatory and the source's small printed “5” remain intact. Word's alternate text-box representations export to a single visible certificate, not duplicate certificates. Human side-by-side review in Word and HOA approval are still required before activation.

## Behavior

- The `original-pdf-v1` renderer imports the original page as a fixed background using FPDI/TCPDF. Only name, block, lot, purpose, issuance day and English month are overlaid. The phase suffix stays blank. No new certificate number, expiry, street or house-number field is printed; numbering/expiry remain in the ledger record.
- Populated text uses the bundled Unicode FreeSerif bold font (12pt, shrinking only down to 9pt). The background's fonts are not replaced. Input that still does not fit returns a field error; it is never truncated or allowed to reflow the page. Missing registered name/block/lot must be corrected before generating a document. Purpose remains optional under existing issuance rules.
- The printed `2026` is unchanged; official issuance/approval of this version outside 2026 is blocked. A future year requires a separately reviewed and approved reference version, not editing this approved record.
- Admin-only registration requires visual-review confirmation. Separate approval requires an audit note plus the existing authorized-signatory confirmation matching the printed `JOSEPHINE ALARZAR` / `SHOAi PRESIDENT`. Names in the DOCX do not establish authority. A different configured signatory cannot silently replace the printed one.
- Until approved, the existing Residency layout remains in use. Reference/template/request previews are private, watermarked and create no certificate, number or issuance notification. Official PDFs are non-interactive; all existing documents remain untouched.
- Background copies are private and content-addressed. Checksums are rechecked when rendering. Shared background objects are retained if registration fails after storage; no cleanup may delete a background used by another version. Failed issued-output storage is cleaned up transactionally.

## Deployment and review

1. Preserve a reviewed database/private-storage backup and resolve the existing independent host-security release gate before production deployment.
2. Install the committed Composer lockfile (FPDI 2.6.9, TCPDF 6.11.4). No server-side Office installation or automated Word conversion is required at runtime.
3. Review/apply pending migrations only under the existing deployment procedure. The new additive migration adds `renderer` (default `text-v1`) and nullable `layout` to certificate templates; it does not modify existing documents or approve records. No live migration was run during implementation.
4. In **Certificate Templates**, use **Review original Residency reference PDF** and compare with the actual original Word document. Then **Register original Residency draft**. Check a real resident's private request draft for fit before approval.
5. Only an authorized administrator, with HOA evidence, should confirm the printed signatory and approve the exact version. Do not change the configured signatory merely to bypass the gate. Unapproved registration is safe to leave pending.
6. Retain private backgrounds and snapshots when rolling back application code. Do not roll back the additive migration after issuing an original-layout certificate without a reviewed recovery plan; removing layout metadata would discard template provenance. Historical output files must never be regenerated automatically.

## Reproducible engineering checks

`php scripts/preview-residency-layout.php` produces only synthetic file previews in `storage/framework/testing/residency-layout-review`, without database writes, template registration or `.env` changes. `scripts/check-residency-layout.py` optionally checks these artifacts using PyMuPDF in an isolated development environment; PyMuPDF is not an application/runtime dependency.

The blank FPDI import preserves page dimensions and extracted source text. At 72dpi, measured source-versus-import differences were at most 2/255 color levels from PDF transparency/antialias processing; this is **not** a byte-identical or pixel-identical file claim. Comparing the populated render with its unpopulated import showed **zero changes outside the mapped blank regions** (2pt antialias allowance). Unicode name extraction, no interactive widgets and the complete draft watermark were checked. These engineering checks are not HOA visual/editorial approval or certification of every PDF viewer/printer/font substitution.

Verification: full SQLite run **352 passed / 2 SQL-only skips**; focused native MySQL 8.4.9 **45 passed**, plus the separate certificate race **1 passed**. The new SQL-only certificate race separately skips on SQLite. Chromium workflow retry **1 passed** after correcting an outer-modal test locator; no serious/critical Axe findings on the exercised page. Pint, Composer validation/audit, production build and layout inspection passed. Detailed evidence and remaining gates are recorded in `shoai-revision-progress.md`.
