# Icon-only record actions — 12 November 2026

## Scope and architecture

Presentation-only local implementation. The confirmed scope is **record actions only**, not every button: primary forms, create/submit/login/cancel, navigation, filtering, pagination, toolbar/bulk menu entries and email links retain text. Existing dropdown entries stay labeled; their record-menu trigger uses an ellipsis icon.

The application registers Filament's native `modifyUngroupedRecordActionsUsing` table hook. This covers all 23 current table definitions (including the intentionally empty read-only household table) without changing handlers, predicates, authorization, URLs, modal schemas, confirmations or success feedback. Thirteen existing-record Edit/View pages explicitly opt into the header-action trait. It does not apply to Create/List page headers or form footer actions. The household-member page opts in only its current-household clearing operation, not Add resident.

Native actions retain labels and explanatory tooltips. Icon selection preserves configured icons, provides semantic defaults where absent, and distinguishes soft deletion (archive box) from permanent deletion (trash). Disabled receipt/setup explanations remain intact. Only opted-in controls receive a minimum 44 × 44 CSS target and visible keyboard outline. No vendor file or generic label-hiding CSS was introduced.

Custom lists and legacy attachments use one Blade control with escaped accessible labels, hidden-from-accessibility SVGs, native link URLs or non-submitting buttons, and hover/focus tooltips. Attachment filenames remain readable beside download icons. Known application labels update with English/Filipino locale changes; arbitrary user content is never translated. Missing View details, Retry failed emails, Revoke and Force delete translations were added; existing attachment translations were retained.

## Action inventory

| Interface | Record operations | Treatment |
|---|---|---|
| Users / Homeowners / tenant relation | View, edit, approval/deactivation, password reset, phase confirmation, archive/restore | Shared row hook; existing-record headers opt in |
| Households / members | Edit, assign head, archive/restore, current-household clearing | Icons; relationship-review workflow unchanged; More actions retains labeled entries |
| Projects | Edit, archive, restore | Icons; Add project and Save Project retain text |
| Complaints | View/edit, reopen, archive/restore, attachment access | Row/header icons; resident submission and priority policy unchanged |
| Service requests | View, approve/reject, permitted preparation/guidance, archive/restore, attachments | Icons; resident certificate PDF restrictions unchanged |
| Certificates | Manager print/download, ready for pickup, collected, revoke, archive/restore | Icons; approval, issuance, physical readiness and collection remain distinct |
| Payments / Dues / schedules | Receipts, proof action when permitted, edit/review, schedule deactivation, archive/restore | Icons; disabled/setup explanations retained; financial behavior unchanged |
| Announcements | View/edit, confirmed email/Important SMS operations, failed-email retry, archive/restore | Icons; no new notification triggers or sends |
| Contact messages | Open, reply, internal note, status, reopen and archive/restore | Direct triggers/icons; secondary dropdown entries retain text |
| Audit / settings / roles | Record details/edit where authorized | Row icons; export toolbar and role permissions unchanged |
| My Concerns | View details and supporting attachments | Shared custom control; Complaint/Inquiry ownership unchanged |
| Legacy portal | Concern details, complaint/request attachments, dashboard receipt download | Shared custom control; receipt numbers remain readable beside download icons and descriptive ticket links retain navigation text |

## Verification evidence

- Final focused icon suite: **6 passed, 42 assertions**, including Filipino accessible names, escaped labels, archive/permanent-delete separation, disabled states, confirmation retention, unchanged primary controls and Admin/Staff row behavior.
- Workflow/RBAC/address/head/project/request/mail regression command: `php artisan test --compact --filter='RecordActionIconsTest|InterfaceRevisionTest|HouseholdHead|Project|ServiceRequest|WorkflowMail|HomeownerAddressForms|RolePermission'`: **141 passed, 1 prerequisite skip, 1,125 assertions**.
- The skipped `ServiceRequestEditingTest::test_cohabitation_issuance_still_completes_the_approved_request_once` was rerun with process-only `CERTIFICATE_COHABITATION_FONT=C:/Windows/Fonts/arial.ttf`: **1 passed, 6 assertions**. No font substitution, source approval outside fixtures or environment-file change.
- `node --test tests/Tooling/*.test.mjs`: **19 passed**, including three new record-icon checks. JavaScript syntax checks passed.
- `npm.cmd run build`: **130 modules**, complete matching build. Existing runtime hero-image URL warnings remain unchanged. No dependency changes.
- Pint ran only on the affected PHP files; no broad worktree formatter or unrelated cleanup.
- Browser checks run through `node scripts/run-interface-browser-checks.mjs` using disposable SQLite/storage, synthetic accounts, array mail, disabled SMS and blocked external requests. **Final rerun: all four Chromium checks passed** at 1280 px and 390 px. Verified icon-only content, accessible names, SVG presence, at least 44 × 44 targets, keyboard focus, Filipino labels, custom concern selection, retained menu labels, and real pickup cancellation/confirmation with one success outcome. Artifacts: `C:/Users/bargo/AppData/Local/Temp/hoa-alert-browser-interface-R9jVbq`; representative mobile household and desktop concern screenshots were visually inspected. External avatar loading is deliberately blocked in fixtures. Initial admin checks exposed a test locator mismatch: Filament dropdown destinations are accessible links, not `menuitem` roles; corrected the assertion without changing application behavior.
- An additional portal/account/receipt run initially returned **67 passed / 3 failed**: all three failures were stale `HomeownerPanelResourcesTest` assertions expecting the former certificate list and resident download action. Corrected those tests to assert the existing tracking-page redirect, unchanged ownership scoping, office-pickup notice, absence of preview/download actions and HTTP 403 for an otherwise valid signed resident download URL. No runtime restriction was relaxed. The final focused rerun of `HomeownerPanelResourcesTest|RecordActionIconsTest` returned **11 passed, 78 assertions**.

No actual email/SMS delivery, production authorization-session testing, production writes or complete cross-browser certification is claimed. Existing local/production records and queued/failed jobs were not processed by this implementation.

## Preparation and subsequent SSH rollout

**Deployment completed after separate user authorization on 12 November 2026.** See [production deployment evidence and rollback instructions](../deployments/record-action-icons-2026-11-12.md). The local-only statements above describe the implementation phase, not the later authorized rollout.

`node scripts/package-record-icons.mjs` packages an explicit presentation/runtime allowlist plus the entire matching `public/build`, with SHA-256 checksums. No migration, `.env`, `.htaccess`, private data, fonts, issued documents or tests are included.

Prepared package: `C:/Users/bargo/AppData/Local/Temp/hoa-record-icons-release-sUlHG5/release.tar.gz`, **56 files**, SHA-256 `c8ffdb8e207cdefe3db7edfb0fee2bb36b741dba98f4c4cd5da99a5832b26907`. Manifest verification against the final runtime workspace returned zero mismatches; the subsequent authorized production installation also verified all 56 files without mismatches.

Deployment requires separate SSH authorization: compare production files, create a private affected-source/build backup, verify the archive and manifest, install the matching source/build with manifest last and retain old hashed assets, then rebuild compiled views and the existing Laravel caches. Check public assets and authorized synthetic record controls without sending notifications. Do not start/restart workers, retry jobs or alter SMS/cron settings. Roll back by restoring the backed-up source and matching assets and rebuilding caches; no database rollback is necessary.
