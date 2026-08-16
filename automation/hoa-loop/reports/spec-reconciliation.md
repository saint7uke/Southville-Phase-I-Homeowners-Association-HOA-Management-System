# v1.2 Specification Reconciliation

Date: 2026-08-17  
Baseline commit: `005b019`  
Baseline verification: 13 tests, 38 assertions, passing

## Binding architecture decisions

The instruction to start from the existing project approves the following backward-compatible interpretation of the brief:

1. Preserve Laravel 12, Filament 4, React 19, Vite 7, and Tailwind 4. Translate the brief's behavior to the installed APIs; do not downgrade major dependencies.
2. Add a dedicated Filament homeowner panel at `/homeowner` while preserving `/portal` until the new panel reaches parity and UAT approves retirement.
3. Add panel-specific session guards backed by the existing `users` provider. Preserve Spatie roles on the canonical `web` permission guard and resolve actor attribution across panel guards.
4. Prefer additive migrations and compatibility adapters over renaming or dropping the working `dues_settings`, `payments`, `service_requests`, or custom audit tables.
5. Keep SQLite for fast automated tests and document/verify a MySQL 8 release lane before production.
6. Preserve the approved HOA landing-page visual direction and hero image while completing missing functional, accessibility, and route requirements.
7. Keep Sanctum until a dedicated dependency-removal cycle confirms it is unused and the full suite remains green.

## Verified implementation matrix

| Area | Status | Existing evidence | Required work |
|---|---|---|---|
| Public landing | Partial | React/GSAP modular landing, responsive navigation, real HOA hero, published announcement feed, reduced-motion handling | Contact form/API, `/homeowner/login` CTAs, How It Works, announcement audience/expiry, accessibility/browser verification |
| Admin panel | Partial | Filament `/admin`, active-role gate, resources and KPI widget | Password/reset/security flows, certificates, complete reports/audit/dashboard |
| Staff panel | Partial | Filament `/staff`, role gate, operational resources and export | Explicit resource allow-list, required dues/announcement permissions, certificates and limited reports |
| Homeowner experience | Partial | Owner-scoped Blade `/portal` supports registration, profile, complaints, requests, announcements and recent payments | Dedicated Filament `/homeowner`, independent guard, owner-scoped resources, proofs and certificates |
| Authentication/RBAC | Partial | Spatie roles/policies and active checks | Cross-panel guard isolation, actor resolver, verification/reset, full 3x3 access tests, least-exposure routing |
| Homeowner records | Partial | User/Homeowner models, approval lifecycle, profile editing | Emergency contact/phase, unique block-lot rule, profile media, delinquency automation |
| Dues/payments | Partial | Dues settings, server-calculated payment recording, XLSX export | Obligation schedules/records, decimal-safe amounts, generation/overdue jobs, proof review, receipts, delinquency |
| Complaints/requests | Partial | Owner-scoped submission and viewing, private complaint attachment, guarded transitions | Full status rules, notes, assignments, multi-file support, notifications, certificate linkage |
| Announcements | Partial | CRUD, published scope, landing/API display | Audience, expiry/scheduling, staff publishing permissions, queued notification and homeowner view |
| Certificates | Missing | None | Schema, issuance/revocation, secure PDF generation/download, request linkage and notifications |
| Reports | Partial | Payment XLSX only | Required CSV/PDF report matrix, authorization, scalable generation and panel pages |
| Audit | Partial | Immutable custom audit model, observer and admin resource | Auth/export/role events, actor panel, privacy redaction/retention, broader model coverage and export |
| Jobs/notifications | Partial | Queue tables plus registration/account notifications | Dues, overdue, workflow, announcement and certificate jobs/notifications; scheduler |
| Tests/operations | Partial | 13 tests/38 assertions pass; production Vite build exists | Workflow/security/media/browser/a11y/MySQL coverage, CI/runbooks, scheduler/worker/backups/manuals |

## Security constraints for implementation

- Fix guard-aware actor resolution before enabling distinct panel guards.
- Homeowner Filament resources must be dedicated classes with query-level ownership constraints; do not reuse Admin resources.
- Other-owner records and private files must resolve as not found or otherwise disclose no record details.
- Staff must receive an explicit resource allow-list and cannot restore/edit soft-deleted or Admin-only records.
- Monetary logic must avoid binary floating-point for persisted business calculations.
- Uploads remain private, validated, authorized, and covered by download/IDOR tests.
- Existing `/portal` route names and behavior remain compatible during migration.

## Implementation sequence

1. Tri-panel/auth foundation and exhaustive access matrix.
2. Account lifecycle and homeowner profile completion.
3. Additive dues/payment obligations, proof review, receipts, scheduler and delinquency.
4. Complaint/request workflows, private media and notifications.
5. Announcement lifecycle/contact workflow and landing alignment.
6. Certificates and secure PDFs.
7. Reports, audit completion and dashboards.
8. Full integration, security, responsive/accessibility, deployment documentation and acceptance evidence.

## Reconciliation result

Phase `00-spec-reconciliation` is accepted. The repository now has a recoverable baseline, there are no unresolved architecture decisions blocking implementation, and the guarded loop may proceed to `01-foundation-tri-panel`.
