# SC-07 — Verified tenant payments for a shared property

Date: **23 October 2026 (Asia/Manila)**. Implementation authorized by the user's request to implement the preceding property-based eligibility recommendation. This records an application-policy decision, not fabricated HOA meeting minutes, document approval or host-recovery sign-off.

## Implemented policy

- The existing primary Owner remains liable; the existing Co-owner fallback still applies when there is no eligible Owner. No obligation, payment or historical liable account is reassigned.
- An eligible Tenant may pay when its stored `landlord_homeowner_id` identifies an eligible, non-deleted Owner **or Co-owner at the same confirmed property** as the liable account. Street spelling and house-number differences do not override confirmed phase/block/lot identity.
- Both Tenant and landlord must have a current, non-deleted user whose account is `Active`; homeowner-record status may be `Active` or `Delinquent`. Delinquency alone does not prevent settlement.
- An unlinked Tenant, Tenant-as-landlord, self-link, different property/phase, missing confirmation, inactive/suspended/pending/rejected resident, or deleted account does not gain eligibility.
- Already supported direct-landlord legacy grouping is retained. **Unresolved legacy identities do not gain the new indirect-landlord access.** No suffix is guessed and no household is merged. Staff must resolve unknown identity through the existing reviewed process.
- Lease/name snapshots are not used as authority to infer a relationship. Existing registration and admin/staff relationship management remain authoritative.

Example: Owner Juan is liable, Maria is an eligible same-property Co-owner, and Ana is Maria's verified Tenant. Staff may record Ana's Cash payment against Juan's existing obligation. The payment retains Juan as `homeowner_id` and Ana as `payer_homeowner_id`.

## Implementation and privacy

`PropertyDuesAccounts::eligiblePayers()` is the shared query for selectors, boolean eligibility, dashboards and financial recording. `payerIsEligible()` queries current database state instead of trusting stale loaded user/landlord relations. A qualified deleted-record check also protects the `landlord()` relationship, which intentionally supports historical `withTrashed` reads elsewhere.

During recording, the property mutex is acquired before the current liable/obligation rows; changed property context produces a localized reload error. Final eligibility uses locking reads for the payer, linked landlord and user-status predicates, so MySQL REPEATABLE READ cannot reuse a pre-wait eligibility snapshot. Existing exact-cent arithmetic, pending reservations, submission tokens, retries and obligation reconciliation remain intact.

`DuesObligationPolicy::view()` applies that eligibility to property-dues reading, alongside the existing owner/manager rights. Dues editing remains forbidden. The homeowner dues list and overview already consume the shared service.

**Payment history and receipt permissions are deliberately separate:** only the liable resident, actual payer and authorized managers retain the existing read rights. A same-property Co-owner/landlord or another eligible Tenant does not thereby gain access to someone else's payment or private PDF. A former Tenant may still read its own historical receipt while its account remains active; unlinking prevents new payments, not historical payer attribution. Inactive-account download restrictions remain enforced.

Cash-only staff/admin recording remains in force. Tenant eligibility does not grant `manage_payments`, enable online payment/proof submission, introduce another certificate/receipt template, regenerate files or create duplicate assessments.

## Changed files

- `app/Services/PropertyDuesAccounts.php`: shared current-state eligibility and optional transactional locking.
- `app/Actions/Payments/RecordPayment.php`: property-context locking and final authoritative eligible-payer selection.
- `app/Policies/DuesObligationPolicy.php`: consistent property-dues read authorization; no payment-history permission expansion.
- `lang/en.json`, `lang/fil.json`: property-context reload guidance.
- `tests/Feature/TenantPropertyPaymentsTest.php`: positive, negative, legacy, Livewire, privacy, cash-only and ledger regressions.
- `tests/Feature/MySqlTenantPropertyPaymentsTest.php`: actual concurrent unlink/deactivation and payment-allocation cases.
- Related audit/checklist documentation. Existing uncommitted SC-01–SC-06 repairs are preserved.

**No migration, live data repair, live account/source approval, credential change, notification delivery, commit, push or deployment.**

## Verification

Tests use fake notifications, private synthetic storage, SQLite memory databases and a newly initialized loopback-only **native MySQL Community Server 8.4.9** instance on port `33187`, database `hoa_native_test_sc07_20261023`. Native tests require explicit destructive-test opt-in and pass `hoa:verify-native-mysql` before execution. `hoa_system` is not a mutation fixture. Evidence identifies the uncommitted worktree based on `c21509a6ce0125504a2439b97883b4366db195f0`; no acceptance commit or remote run is implied.

| Final check | Actual result / retained evidence |
|---|---|
| Full sequential SQLite suite | **561 passed, 13 native-only skipped; 6,284 assertions**, zero errors/failures; JUnit time 1,215.50 seconds. `storage/framework/testing/sc07-full-sqlite-final.xml` |
| Focused native MySQL 8.4.9 | **74 passed / 368 assertions / no skips**, zero errors/failures; JUnit time 156.84 seconds. Includes the three new unlink/deactivation/allocation races and two existing concurrent payment tests. `storage/framework/testing/sc07-native-final.xml` |
| Additional native legacy-entry recheck | **1 passed / 4 assertions**, 37.73 seconds. Covers the final legacy recording entry-point regression. `storage/framework/testing/sc07-native-legacy-recheck.xml` |
| Focused related SQLite suites | **73 passed / 513 assertions**; separate original-Butaw regression **29 passed / 197 assertions** |
| Formatting and syntax | Full Pint check, scoped Pint, five changed/new SC-07 PHP files' syntax checks and `git diff --check` passed |
| Production build | Vite 7.3.7 passed (44.39 seconds); existing unrelated hero-image runtime-URL warning retained |
| Translation parity | **1,193 matching keys**, no missing keys; 41 existing inventory review candidates remain |

The native selection was collected before the final legacy-entry regression was added; that additional case then passed separately on native MySQL. The full SQLite result includes all 38 new feature cases. The three new native-only cases are skipped on SQLite, not counted as SQLite passes. This focused native selection is not a full native system acceptance run. Machine-generated XML/logs are retained as ignored local evidence, not committed application files. The standalone port-33187 test server was shut down with its own `mysqladmin` connection after verification; no unrelated database service was stopped.

Initial evidence retained:

- Existing financial/property checks: **45 passed / 165 assertions**.
- Initial expanded related SQLite selection: **73 passed / 513 assertions**.
- The first negative-case snapshot assertions exposed SQLite's inserted-model versus reloaded-row numeric types/order, not ledger mutation. Compare database-reloaded snapshots on both sides; all eligibility-denial assertions remain intact.
- A concurrent full-suite attempt reported four certificate-background checksum errors while multiple PHP suites shared the default fake disk. The new tests now use process-specific fake storage. The final sequential full suite passed; no original certificate source, checksum check or renderer was changed to conceal the failures. Initial evidence: `storage/framework/testing/sc07-full-sqlite.xml`.
- The first native selection executed all concurrency assertions but failed three teardown rollbacks: the historical `2026_10_04_181143` down migration tried to restore block/lot uniqueness over valid shared-property fixtures. The new concurrency test now uses guarded `DatabaseTruncation`/fresh disposable schemas and resets migration state before another test style runs. Historical migrations and legitimate records are unchanged.

### Existing rollback limitation

Do **not** blindly roll a populated shared-property database back through the old block/lot-uniqueness removal. Its down migration cannot reinstate that unique constraint while legitimate tenants/co-owners share block/lot values. Use reviewed backups and a forward-fix deployment strategy. No live rollback or automatic deduplication was performed; this pre-existing limitation is not presented as an SC-07 application failure or silently “fixed” by deleting records.

### Acceptance boundaries

Livewire checks cover both payer selectors, stale selection rejection and staff recording; existing shared-receipt regressions cover saved-file delivery. These are not new authenticated Playwright or physical-print acceptance. No financial UI layout or original PDF design changed. Full native system acceptance, real queue/scheduler operation, HOA editorial/document approvals and independent **SEC-01** recovery remain separate release requirements.
