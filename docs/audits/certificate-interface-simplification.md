# Certificate-page workflow simplification — 2026-10-21

## Why the confirmations existed

Source confirmation is an authorized, audited approval of a particular document version and, for Residency, its printed signatory. It is not a per-homeowner review requirement. Reviewing a PDF, opening saved receipts and navigating to creation do not approve a source. The old header presented these unrelated tasks as seven equally prominent buttons, obscuring that distinction.

## Updated interface

- **New document**: the primary action, opening the existing certificate/payment-document form.
- **Monthly receipts**: a secondary action opening saved receipt selection and View/Print/Download links. It has no submit/confirmation button.
- **Document setup**: a neutral administrator-only dropdown containing source-review links and any outstanding approvals. No separate template-management page is introduced.

Residency and Resibo confirmation actions hide once the current uploaded source is approved. Residency readiness also checks that the current authorized signatory matches the original document; a changed source/signatory requires review again. Cohabitation confirmation appears only when an existing draft awaits approval. Review links remain available inside the setup menu after approval.

Setup dialogs explicitly explain the one-time nature and use **Confirm source**, rather than an ambiguous generic confirmation label. Staff retain everyday document/payment actions under existing permissions, but cannot mount or execute administrator-only setup actions. Payment and certificate permissions remain separate. Domain approval checks, evidence notes, snapshots, private PDFs and issuance confirmations remain intact.

## Scope and safeguards

Changed `ListCertificates`, read-only readiness helpers in `CertificateTemplates` and `ButawReceiptSource`, English/Filipino presentation copy, and focused Livewire/browser regressions. No migration, role grant, live approval, original-document redesign, financial update or historical PDF regeneration is included. Grouping actions is presentation only; hidden controls are not relied upon as the backend authorization boundary.

Existing action identifiers are retained for callable-action compatibility. Browser expectations now open the dropdown before selecting source actions and recognize one-time approvals that have already completed. New coverage checks three-versus-two header controls for admin/staff, responsive widths, keyboard return focus, bounded Axe results and confirmation-free saved-receipt viewing. Exact results are appended after execution; earlier failed test-harness assertions are retained in ignored logs, not relabelled as passes.

## Verification

- Focused SQLite/Livewire/document regressions: **41 passed, 239 assertions**, 68.72 seconds (`storage/logs/certificate-interface-focused-final.txt`). Covers grouped action execution, completed approvals hiding, changed-signatory re-review, Cohabitation approval, staff direct-mount denial, receipts, preview privacy and unchanged issuance/storage protections.
- Chromium header/interface acceptance: **2 passed** (administrator and staff), including 320/768/1440 widths, setup-menu keyboard focus, no serious/critical Axe findings on the exercised administrator view, and saved-receipt dialogs without submission buttons (`certificate-interface-browser-final.txt`). The first run's staff modal timed out while its Livewire request remained pending; the harness now waits for the matching successful response before inspecting the dialog and waits for close completion. No authorization or form assertions were removed.
- Production build passed, retaining the existing runtime hero-image warning. Changed PHP/JavaScript syntax and whitespace checks passed. English/Filipino dictionaries have **1,082 matching keys**. The first focused run used an obsolete false-versus-null test expectation; the corrected complete focused run passed as recorded above.

Full SQLite and the updated already-approved-source receipt browser flow are recorded below after completion. No full cross-engine or native database rerun is claimed for this presentation-only increment; earlier document/database evidence remains in the linked audit history.

- Already-approved-source Cash receipt browser regression: **1 passed, 2.9 minutes**, Chromium (`certificate-interface-receipt-browser-final.txt`). Covers the grouped review link, absence of the completed source-confirmation action, original receipt preview/generation and matching private View/Print/Download bytes. An earlier attempt generated the receipt but exhausted its total 180-second test budget during download under simultaneous suite load. The final workflow allowance is 300 seconds; assertions and application behavior were unchanged.
- Immediate post-approval visibility: **3 passed, 49 assertions**, including checking the same Livewire component immediately after Cohabitation approval, not only after a reload (`certificate-interface-immediate-visibility.txt`).
- The audit-owned browser server on 8019 was stopped after verification. No live source approvals, original-file changes, role grants or financial changes were performed; all browser writes were confined to synthetic isolated fixtures.
- Final full SQLite suite: **394 passed, five MySQL-only skips, 4,112 assertions**, 668.77 seconds (`storage/logs/certificate-interface-full-sqlite.txt`). No new failures remain. Full native MySQL and Firefox/WebKit reruns were not performed for this UI increment. Final Pint, changed-file syntax and whitespace checks passed; the ordinary application and XAMPP database were left running.
