# Household head consistency

Date: 9 November 2026 (Asia/Manila). Implemented and verified locally, then deployed in a separately authorized SSH rollout. Production household/member records were not repaired or reassigned. See `../deployments/household-head-2026-11-09.md` for deployment evidence.

## Audit and authoritative state

The preceding read-only production audit found household 2 pointing to member 1 (Relationship Relative), while current member 7 had Relationship Head. Archived member 6 retained a historical Head label. No actual person was selected or corrected as part of this implementation. These are prior audit findings, not a new production mutation or an assumption about the actual family head.

`households.head_member_id` is authoritative. `ManageHouseholdHead` is the shared write path that validates the complete current roster and synchronizes the mirrored current Head relationship. Existing conflicts are rendered as warnings rather than repaired during reads. A head is not necessarily the registered owner, the eldest resident, or a person with a portal account.

## Behavior and interfaces

- Members page: **Set as Household Head** opens the complete relationship review. Households Edit retains its selector, disabled until **Review household head and all relationships** is enabled. Both paths share the schema and domain action.
- The internal review payload contains `head_member_id`, `head_revision`, a member-ID-keyed `relationships` map, and accepted `review_confirmed`. Household Edit additionally sends `review_head=true`. No new HTTP API or database column is introduced.
- Every current member (non-archived, membership not ended) must be represented exactly once. Only the selected person can be Head. The former head's replacement relationship is deliberately blank until explicitly selected. The reviewer must confirm all relationships.
- Eligible heads have residency Resident or Temporarily away. Archived, ended, Moved and other-household members are denied. Ordinary member forms cannot independently create, promote or demote a head. Unchanged legacy selected relationships remain readable/editable without silently correcting them.
- A revision fingerprint includes the selected head and ordered member identity/name/relationship/residency/archive/end/link state. Changes after opening the review reject the submission with instructions to reopen it. Validation fingerprints the locked current roster rather than re-reading an older transaction snapshot. Transactions lock households before members; affected property-phase confirmation and synchronization paths use the same order. Multi-household synchronization locks parents in ID order and refuses a changed membership whose parent was not locked.
- Clearing is explicit: current Head labels become Other, the pointer becomes null, and `needs_review=true`. Ended/archived relationships remain unchanged. Unknown property/application review flags are not cleared by ordinary saves or head confirmation.
- Departure, account/homeowner deletion or archival clears an outgoing head's pointer without selecting another person. Historical Head labels remain. Restoring an active former head, or returning a manual former head from Moved, records Other pending explicit head review. Restored records whose membership remains ended keep historical labels.
- Head-only/contact/review updates do not regenerate household property keys or phase values. Dues, payments, owner/landlord links and issued documents are not rewritten. AuditObserver records the pointer and relationship changes within the same transaction.
- Management pages, homeowner summaries and new provisional PDF/Excel exports show the canonical current head and unresolved warnings. Excel metadata remains explicit text; HTML remains escaped. Existing stored exports/documents are not rewritten.

## Read-only rollout report

`php artisan hoa:audit-household-heads --json`

This command reports active household IDs, selected member IDs, current Head-label IDs and issue categories. It never selects, clears, flags or repairs records and omits personal names/addresses. There is intentionally no repair/apply switch. Household 2 requires an authorized HOA reviewer to identify the actual head and explicitly review all current relationships.

## Verification

Local verification on 9 November 2026:

- Selected PHP regressions: **239 tests, 1,658 assertions passed**. Includes household/head lifecycle, Admin/Staff permissions, homeowner summaries, registration/profile/address identity, account lifecycle, reporting, exports and historical snapshots. Result: `storage/framework/testing/household-head-final.xml` (local generated artifact).
- Final focused head-assignment rerun: **38 tests, 152 assertions passed**, including the final transaction guards. Result: `storage/framework/testing/household-head-focused.xml`.
- Actual Chromium controls: **3 tests passed** on the final rerun. Both assignment entry points, complete relationship review, prior-head demotion, cancellation, one request/one success outcome, validation failure without success, and confirmed clearing were exercised. Runner: `node scripts/run-household-head-browser-checks.mjs`.
- JavaScript tooling: **10 tests passed**; production Vite build passed. The pre-existing community-hero image-resolution warning remains unchanged.
- Scoped PHP formatting and `git diff --check`: passed.

Tests use isolated SQLite and synthetic accounts. The dedicated browser runner uses a new temporary database, array mail and no production connection. Additional coverage verifies rollback and audit records, stale state, forged/null-ID Head payloads, legacy identity preservation, historical/returned membership, and PDF/Excel escaping/warnings. No test emails or production database operations were performed.

Actual simultaneous multi-process MySQL contention is not established by SQLite tests; revision rejection is tested deterministically after concurrent-state changes. There is no claim of production authenticated workflow verification.

## Deployment boundary

See `docs/deployments/household-head-source-manifest.txt` for the exact 18 source/view/translation paths. Deploy the matching complete Vite build and regenerate autoload/config/view caches only in a separately authorized rollout, after private file/database backups and the read-only report. No new migration is required.

The original implementation phase excluded SSH deployment; the subsequent user-authorized rollout is recorded separately above. It did not correct production records, issue official certificates, send email, drain queues, retry failed jobs or change scheduler/workers. Production queued/failed jobs, `.env`, the user-owned root `.htaccess`, private uploads/fonts and unrelated workspace changes remain preserved.
