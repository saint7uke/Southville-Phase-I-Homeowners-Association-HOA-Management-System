# Source-brief completion audit

Date: 2026-09-20  
Source: `automation/hoa-loop/spec/brief-extract.txt` (v1.2, 798 lines)  
Interpretation authority: approved decisions in `spec-reconciliation.md`

## Method

The original brief was reread section by section rather than treating the normalized scope or prior implementation plan as sufficient proof. Each functional area was checked against current routes, providers, policies, actions, models, forms, jobs, notifications, tests, browser results, operational documentation, and release evidence. Exact dependency-major and legacy-surface differences are governed by the approved reconciliation: supported newer Laravel/Filament/React/Vite/Tailwind versions remain; the custom immutable audit implementation remains; `/portal` remains as a compatibility surface; and the user-approved landing direction remains instead of reverting its visual design.

## Requirement evidence

| Brief area | Current evidence | Result |
| --- | --- | --- |
| Architecture and four surfaces | Laravel routes plus independent Admin, Staff, and Homeowner Filament providers/guards; React landing at `/`; `PanelFoundationTest` | Implemented and machine passed |
| RBAC and cross-panel isolation | Spatie roles, policies, explicit Staff resource allow-list, query-level owner scoping, 3x3 access tests, prior live 12/12 four-browser role matrix | Implemented and machine passed |
| Authentication and account lifecycle | Panel-native login/reset, Homeowner verification, throttling, lifecycle transitions, session revocation, auth audit events | Implemented and machine passed |
| Homeowner records and self-service | Complete profile, derived age, normalized property identity, private re-encoded photo, password change, address uniqueness, owner-only resources | Implemented and machine passed |
| Dues, payments, proofs, receipts, overdue and delinquency | Queued/idempotent obligations, exact-cent calculations, proof review, private receipts, overdue and delinquency jobs/notifications | Implemented and machine passed |
| Complaints and requests | Forward-only transitions, required terminal notes, staff assignment, private multi-file attachments, owner timeline/detail views, notifications | Implemented and machine passed |
| Announcements and contact | Publish/schedule/expiry/audience rules, urgent/category presentation, queued blast, unread state, validated/throttled contact submission and Staff notification | Implemented and machine passed |
| Certificates | Three required types, standalone/request-linked issuance, concurrency-safe `CERT-YYYY-XXXX`, private PDF, signed delivery, notification, Admin-only reasoned revocation | Implemented and machine passed |
| Reports and exports | Required Admin/Staff report matrix, filters, CSV/PDF, private queued CSV, formula neutralization, archived display values, audit export | Implemented and machine passed |
| Audit and dashboards | Immutable panel-aware/auth/export/role/model audit trail; Admin financial/KPI/charts/tables; Staff operational-only widgets; Homeowner self-service widgets | Implemented and machine passed |
| Universal forms and uploads | Server-authoritative rules, decimal money, private validated media, controlled statuses, confirmations, normalized email/property/person names | Implemented and machine passed |
| Landing page | Approved HOA hero direction, About, six services, required four-step How It Works sequence, announcements, benefits/community, contact, CTA/footer, GSAP and reduced motion | Implemented; updated landing passed 24/24 across Chromium, Edge, Firefox, and WebKit |
| Production bootstrap | Role seeder plus environment-driven `AdminUserSeeder`; no credential overwrite or role escalation; demo seeder refuses production | Implemented; 3 focused tests passed |
| Deployment and operations | Public document-root, queue/scheduler, readiness, backup/restore, monitoring, rollback, secure first-Admin and UAT runbooks | Prepared; production-specific values and operator approval remain external |
| ISO/IEC 25010 evaluation and UAT | Questionnaire, sample thresholds, witnessed task matrix, defect/approval record | Instrument prepared; representative human execution remains mandatory |

## Gaps found and closed in this audit

1. Admin-created/edited identities did not consistently enforce the brief's Unicode name rules or whitespace/case normalization. One shared `PersonName` normalizer now covers public registration, Homeowner self-edit, Admin user management, and Homeowner operational forms. Numeric/special-character names are rejected server-side.
2. The landing page lacked the explicit Register → Verify → Access Your Panel → Manage Everything section. A semantic ordered timeline was added with GSAP reveal hooks, responsive vertical/horizontal layouts, and reduced-motion compatibility.
3. A fresh production database could seed roles but had no safe first-Admin bootstrap path. `AdminUserSeeder` now requires approved environment values, applies the password policy, creates only an unused identity, is idempotent without rotating credentials, and refuses implicit elevation. `DatabaseSeeder` now refuses production because it contains demonstration accounts.

## Current verification

- Guarded full verifier refreshed on 2026-09-27: protected hashes, Pint, two independent SQLite executions of 146 tests / 582 assertions, 107-module production build, and route-cache create/clear compatibility passed.
- Disposable XAMPP MariaDB/MySQL-compatible lane: 138 tests / 552 assertions passed in 142.80 seconds; `hoa_system_ci_test` was removed and independently verified absent.
- Production Vite build: 107 modules passed.
- Release candidate `d1ed44814913e61ed0c30b92e8a1335b67a52da2`: GitHub run 36307753852 passed SQLite, native MySQL 8.4, and all 10 configured browser tests in Chromium, Firefox, and WebKit on pinned Ubuntu 24.04 with current v7 official actions. The candidate also prevents stale queued certificate notifications from failing after their certificate is removed and includes reliable local XAMPP runtime launchers.
- Current dependency advisories: Composer reported none; npm reported zero vulnerabilities.
- Protected baseline hashes remain unchanged and matching.

## Remaining acceptance gates

No additional unimplemented application requirement was identified by the source-brief audit. The current candidate `d1ed44814913e61ed0c30b92e8a1335b67a52da2` completed the five-job SQLite, native MySQL 8.4, Chromium, Firefox, and WebKit matrix in [GitHub Actions run 36307753852](https://github.com/saint7uke/Southville-Phase-I-Homeowners-Association-HOA-Management-System/actions/runs/36307753852).

Release acceptance remains unproven until representative Admin/Staff/Homeowner UAT and ISO scoring complete, manual NVDA/real-Safari/200%-zoom/document-print checks pass, production domain/database/SMTP/storage/backup/monitoring settings are approved, and deployment is explicitly authorized.
