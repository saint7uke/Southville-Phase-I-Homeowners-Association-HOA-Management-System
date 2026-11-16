# Approval-only request-bound certificate previews

## Superseding local revision — 12 November 2026

The prepared office-pickup release removes all resident certificate PDF actions and denies resident PDF/download/preview endpoints regardless of approval. Admin/Staff approval-only preview and existing source/actor checks remain. Issued files remain preserved for authorized management access. Resident notifications link to request tracking, with separately recorded pickup readiness and collection. The earlier implementation evidence below remains historical; this revision is local and requires separate deployment. See [release evidence](../deployments/interface-revisions-2026-11-12.md).

## Rule and boundaries

Admin, staff, and homeowner request tables show **Preview draft PDF** only for a non-archived Residency request whose current status is exactly **Approved**. Pending, Processing, Rejected, and Completed requests do not offer the action. Their direct request-bound PDF URL returns HTTP 403 after existing authorization/ownership checks; archived requests remain excluded by route binding and return 404.

The token-based preview reader retains its existing invalidation of unissued request-bound previews when approval changes. The HTTP prepared-preview endpoint also checks current approval after token ownership/expiry checks, so an already consumed request-bound token cannot generate another draft after completion. Domain-level issuance retry/idempotency semantics remain unchanged. Completed requests retain their authorized existing issued-document download action and stored bytes.

Cohabitation's informational HTML guidance is not a PDF and remains available under its existing authorization rules; approved managers still enter their panel's existing preparation workflow. Standalone manual certificate preparation, source review, signatory/font/printed-year gates, issuance permissions, and existing issued PDFs are unchanged. No migration, approval changes, certificate regeneration, request cleanup, or production record edits are part of this change.

## Files and verification

Runtime changes: shared service-request table, homeowner service-request resource, `CertificateDraftController`, and English/Filipino dictionaries. Tests add `ServiceRequestPreviewApprovalTest` and update the older guidance tests to use Approved Residency fixtures rather than Pending ones.

Coverage includes every request status in all three panels, genuine draft-mode PDF generation for Approved requests, no issuance/preview allocation/notifications/status changes from request preview GETs, archived requests, unauthorized/unverified users, direct URL rejection, unissued stale tokens, consumed tokens after completion, preserved stored-document downloads, and the translated HTTP error. Tests use isolated SQLite data and synthetic records; no production request or certificate is used.

### Local results (2026-11-08)

- Focused approval suite: 21 tests passed, 187 assertions.
- Combined request, preview, certificate, and authorization regressions: 102 tests, 99 passed, 3 skipped, 928 assertions; no failures or errors.
- The three skips require licensed Arial: Cohabitation issuance/snapshot coverage in `PhaseTwoDocumentsTest`, issuance completion coverage in `ServiceRequestEditingTest`, and certificate-resource approval coverage in `IntegratedResidencyCertificatesTest`.
- JavaScript tooling: 10 tests passed. Production Vite build passed, retaining the existing unresolved `/images/southville-community-hero.webp` warning.
- Pint checks for the touched PHP files and `git diff --check` passed.
- No browser run was performed for this change. Visibility was exercised through actual Livewire resource components across all three panels; this is not a claim of browser or production acceptance.

## Deployment preparation

Local implementation only. Deployment inputs are listed in `docs/deployments/service-request-preview-approval-files.txt`. Deploy the five reviewed PHP/translation files and matching complete `public/build` together after backing up the existing affected files and build outside the web root. Refresh Laravel configuration/view caches and restart workers through the normal deployment process. No font, `.env`, storage upload, migration, vendor, or test fixture is included.

Production request 5 must remain unchanged. Use designated test records to verify the hidden button and HTTP 403 before approval, then a watermarked preview after approval. Completed records must continue to serve their existing issued PDF. A production deployment and authenticated smoke test require a separate request; this audit does not claim they have occurred.

## SSH rollout completed

At the user's subsequent request, deployed the reviewed runtime files and complete matching asset build through SSH (server time 2026-11-08 17:42 UTC). The uploaded archive SHA-256 was `c2e1def6185b8c9939f464ed37652c956166290f6509c99c5255a489b9f27b39`. Production source diffs were checked before replacement and contained only the planned approval guards and translations.

Backup: `/home/u130796816/hoa-preview-approval-20261109/backup.tar.gz`, outside the web root with mode 600. PHP syntax checks passed; Laravel caches were cleared, configuration/views rebuilt, workers signalled to restart, and maintenance mode ended. Deployed source files and manifest matched the staged package. Existing asset chunks were retained for already-open browser sessions.

Post-deployment checks: login HTTP 200, updated translation asset HTTP 200, unauthenticated admin requests and request-draft URLs HTTP 302 to authentication. The production `.env` checksum was unchanged. Read-only checks before and after showed request 5 already Completed with exactly one certificate; neither was modified, and no preview or certificate was generated.

Authenticated browser smoke tests across all three panels remain outstanding. HTTP guest checks establish availability/authentication protection, not authenticated approval behavior; the latter remains covered by the local automated suites until separately verified in a browser.
