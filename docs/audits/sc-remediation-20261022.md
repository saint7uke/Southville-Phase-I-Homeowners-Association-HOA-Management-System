# Audit remediation — 22 October 2026

## Scope and release boundary

Baseline: `c21509a6ce0125504a2439b97883b4366db195f0`. This is the authorized follow-up to the read-only scan, not a new whole-system certification. Changes are uncommitted. No deployment, push, live migration, data repair, source approval, live account provisioning, credential rotation or real notification delivery is included.

**SEC-01 remains an external production-release blocker.** Local source changes, dependency audits, synthetic tests and native MySQL results do not establish host trust. Independent recovery evidence is still required by [the security handoff](security-recovery-handoff.md).

SC-07 was deliberately held in this 22 October increment. On **23 October 2026**, the user authorized the recommended property-based rule; see [the SC-07 implementation and current verification](sc07-tenant-property-payments.md). Eligibility now supports a valid same-property Owner/Co-owner landlord without inferring permission from sharing an address alone. The results below describe the earlier increment unless explicitly dated otherwise.

## Findings and implemented remedies

| ID | Severity / classification | Confirmed cause | Implemented remedy and regression evidence |
|---|---|---|---|
| SC-01 | High / application defect | Loaded original complaint status was trusted during persistence. | Shared `UpdateComplaint` locks and reauthorizes the current row, validates transitions and permitted fields, and maps errors to `data.*`. The model also wraps ordinary Eloquent updates in a row-locked transaction and rejects a changed status. Stale finalized edits, reassignment, forged staff fields, resolution notes, reopening and preserved form input are tested. |
| SC-02 | High / application defect | Generation assessed an old schedule object without refreshing under lock. | A fresh preliminary applicability check avoids scanning inactive schedules; each assessment transaction reloads and locks the schedule before property/resident locks. Amount, due day, active state, dates and frequency come from that locked instance. Tests cover stale amounts, closure, changed start dates, activation, idempotency and real concurrent writers. |
| SC-03 | Medium / application defect | Closure could replace a finite historical end with the current month. | Closure never extends an earlier finite end. Out-of-range generated assessments stop closure with review guidance, without rewriting them. `DuesSchedulePeriods` shares sorted name mutexes and inclusive month-overlap checks with create/update/close; conflicts use locking reads. Existing schedule locks follow name locks. Table confirmation failures show a readable notification. |
| SC-04 | Medium / application defect | Permission synchronization committed independently of its audit entry. | Lock the current role and synchronize permissions plus immutable audit evidence in one transaction. Revalidate protected role rules against the locked role. Evict Spatie caches on failure, successful commit and enclosing transaction rollback, including committed nested children. Tests inject audit failure and warm caches before nested rollback. |
| SC-05 | Medium / security-related application defect | Operational recipients were selected by role/status only. | Complaint, request and contact alerts require their corresponding module permission when selected and again through Laravel's queued-delivery `shouldSend` hook. Requery the recipient instead of trusting serialized roles. Inactive/deleted/non-staff/revoked recipients fail closed. Older payloads infer permission only from known module destinations; unknown destinations are suppressed. Tests include the real queued notification pipeline with a blocked mail channel. |
| SC-06 | Medium / diagnostic defect | Landlord/billing diagnostics used street-sensitive legacy address keys. | Landlord checks call the shared property resolver with selected address fields. Persisted billing groups prefer `property_key`, falling back to legacy grouping only when unresolved. Missing suffixes are explicit review observations, not guessed matches. Tests distinguish suffixes, same-property street spellings and legacy uncertainty, and assert no diagnostic writes. |
| SC-07 | Medium / policy ambiguity, decision subsequently authorized | A Tenant may have a Co-owner landlord while a different Owner is liable. | **Implemented on 23 October after the user's approval.** Verified same-confirmed-property landlord links now drive payer selection, recording and dues reading; payment-history privacy and liable ownership remain unchanged. See the separate follow-up evidence. |
| SC-08 | High tool rating / development dependency advisories | Installed Vite 7.3.6 and `follow-redirects` 1.16.0 were affected. | Targeted compatible lockfile updates only: Vite **7.3.7**, `follow-redirects` **1.16.1**. No forced or major upgrade. Full and production-only npm audits report zero advisories after update. This does not demonstrate or rule out application/host exploitation. |
| SC-09 | Low / application presentation defect | Literal UI translation calls lacked dictionary entries despite key parity. | Add `Close`, `Download`, `Certificate`, document-integrity/setup errors and new complaint/dues errors to both dictionaries. **1,192 matching keys**, zero parity errors. Rendered Filipino output and placeholder preservation are tested. Human editorial approval is not claimed. |
| TEST-01 | Low / test-workflow defect | Previously exported HTML could retain deleted build hashes. | `npm run test:browser:static` enforces **build → guarded in-memory fixture export → local asset preflight → Playwright**. Missing fixtures/assets fail before browser execution. Add Node preflight regressions and the workflow to each CI browser matrix member, retaining failure artifacts. Browser assertions are unchanged. |

### Preservation and compatibility details

- Existing complaint finalization/reopening, audit observers and homeowner notifications remain in place. Admin/staff both use the shared edit action. An unchanged historical assignee remains valid for editing; a changed nonempty assignee must be current active staff. This avoids rejecting older records or an administrator automatically recorded by the existing workflow.
- New lock order for schedule mutation is **sorted equivalent-name mutexes → schedule row**. Generation uses **schedule row → property mutexes → resident/obligation checks** and does not request name mutexes. A concurrent rename detected after waiting produces a reload error instead of proceeding with an unlocked name.
- Financial snapshots, obligations, payments, source documents, certificate PDFs, account identities and payer policy are not rewritten. There is no migration.
- Billing diagnostic grouping uses current stored property identity; obligations lack a historical property snapshot. A duplicate group is a review observation, not proof of duplicate historical debt or authority to merge records.
- The notification change does not process existing queue jobs or remove their private stored payloads. Recognized legacy queued alerts acquire the current permission check when processed through the normal application worker.

## Verification evidence

### Changed files and modules

| Module | Changed/new files |
|---|---|
| Complaints | `app/Actions/Complaints/UpdateComplaint.php` (new), `app/Models/Complaint.php`, `app/Filament/Resources/Complaints/Pages/EditComplaint.php` |
| Dues | `app/Services/DuesSchedulePeriods.php` (new), `app/Actions/Dues/{SaveDuesSetting,CloseDuesSetting,GenerateSettingObligations}.php`, `app/Filament/Resources/DuesSettings/Tables/DuesSettingsTable.php` |
| Permissions / alerts | `app/Actions/Roles/UpdateRolePermissions.php`, `app/Notifications/OperationalAlert.php`, `app/Observers/WorkflowNotificationObserver.php` |
| Diagnostics | `app/Console/Commands/AuditDataIntegrity.php`, `app/Support/PropertyIdentity.php` |
| Presentation / dependencies | `lang/en.json`, `lang/fil.json`, `package.json`, `package-lock.json` |
| Tooling / CI | `scripts/static-browser-assets.mjs`, `scripts/run-static-browser-checks.mjs` (new), `.github/workflows/ci.yml` |
| Regressions | `tests/Feature/AuditRemediationTest.php`, `tests/Feature/MySqlAuditConcurrencyTest.php`, `tests/Tooling/static-browser-assets.test.mjs` (new); `tests/Feature/ReleaseReadinessTest.php` |
| Evidence | This report; `system-wide-audit.md`, `shoai-revision-progress.md`, `localization-coverage.md`, `native-mysql84-acceptance.md` |

**No schema migration, route, payment eligibility predicate or source document changed.**

### Commands and results

Environment: Windows/XAMPP, PHP 8.2.12, Laravel 12.69.2, Filament 4.13.4, Node 24.21.0/npm 11.19.0. Existing records were not used as mutation fixtures.

| Check | Actual result |
|---|---|
| Baseline full SQLite | User-approved audit baseline: 503 passed, 6 native-only skips; 5,923 assertions. |
| Initial focused implementation run | 40 tests; two test-fixture/assertion failures, retained in the work record. Corrected guarded status fixture writes and the domain-error scenario; did not relax required fields or authorization. |
| Focused request/complaint/localization regression | 40 passed / 2,630 assertions. |
| Focused financial/audit regression | 47 passed / 188 assertions. |
| First full SQLite implementation run | 519 passed, 10 native-only skips / 6,038 assertions; 520.94 seconds. Later follow-up tests and cache/compatibility refinements require the final rerun below. |
| Final full SQLite | **523 passed, 10 native-only skips / 6,053 assertions**, 328.93 seconds; 533 total. Evidence: `storage/framework/testing/sc-remediation-sqlite-final.xml`. All four new native race cases are among the explicit SQLite skips. |
| Final native MySQL financial/audit selection | **57 passed / 233 assertions**, no skips; 221.74 seconds. Six actual concurrent row-lock/payment tests are included. Evidence: `storage/framework/testing/sc-remediation-native-final.xml`. |
| Native server identity and isolation | **MySQL Community Server 8.4.9**, not MariaDB; preflight passed. Separate loopback port **33186**, fresh datadir `storage/framework/testing/mysql84-sc-repairs-20261022`, disposable database `hoa_native_test_sc_repairs_20261022`, test-only database-scoped login. No Windows service or existing XAMPP database was changed. |
| Initial native attempts | First selection: 18 passed plus one worker startup timeout. Isolated complaint recheck exposed a new-validator compatibility issue with an unchanged historical admin assignee. Fixed, explicitly tested, and included in the passing final selection. Earlier XML files are retained, not relabelled as passes. |
| Initial combined static browser run | **14 passed, 1 WebKit admin-navigation timeout**, no retries; 8.5 minutes. Chromium/Firefox and remaining WebKit cases passed. Initial trace/screenshot/error context retained under `storage/framework/testing/static-browser-results/`. |
| Isolated failed-case recheck | WebKit admin navigation **1 passed**, no retries; 1.6 minutes. Separate evidence under `storage/framework/testing/sc-remediation-webkit-recheck/`. Not treated as a clean combined pass. |
| Final combined static browser run | **15 passed**, no skips/retries; **4.3 minutes** across Chromium, Firefox and WebKit. Fresh 11-fixture preflight passed. Navigation/guidance cover 320/768/1440 widths, light/dark, keyboard and bounded Axe checks. Separate artifacts: `storage/framework/testing/sc-remediation-static-final/`; `.last-run.json` reports passed with no failed tests. This clean combined pass does not erase the earlier timeout. |
| Tooling regression | Two Node tests passed: stale/missing assets, missing fixtures, traversal rejection and no-bundle rejection. |
| Formatting / syntax / dependencies / build | Full Pint and diff checks passed. **514 PHP files** in `app`, `bootstrap`, `config`, `database`, `routes`, `resources`, `tests`, `scripts`: zero syntax failures. Node syntax checks passed. Composer validation/platform/security checks passed. Final Vite **7.3.7** build passed in **13.71 seconds**; existing hero-image runtime-resolution warning remains, but the referenced file exists (178,290 bytes). npm full and production-only audits: zero reported advisories. |
| Existing database, read-only only | At 22:34 Asia/Manila, actual **MariaDB 10.4.32**, explicitly `START TRANSACTION READ ONLY` followed by `ROLLBACK`: all six integrity checks and all seven updated review observations zero. One household review flag, nine queued jobs and zero failed jobs remain unchanged. This is separate from native-engine evidence and not proof of worker health. |

Private ignored XML/browser files are local evidence, not committed release artifacts. CI uploads its own sanitized failure evidence. Earlier failures and test counts remain distinct from final reruns.

The isolated MySQL server was cleanly shut down after the passing native selection (normal shutdown recorded in its private log). No other database service was stopped or changed.

### Reproduction commands

```powershell
php vendor/bin/phpunit tests/Feature/AuditRemediationTest.php
php vendor/bin/phpunit --log-junit storage/framework/testing/sc-remediation-sqlite-final.xml
npm.cmd run test:tooling
npm.cmd run test:browser:static
php vendor/bin/pint --test
php composer.phar validate --no-check-publish
php composer.phar check-platform-reqs
php composer.phar audit
npm.cmd audit
npm.cmd audit --omit=dev
php scripts/audit-localization.php
git diff --check
```

The browser runner defaults to Chromium, Firefox and WebKit serially with zero retries. It never starts or attaches to a real application server. `--prepare-only` builds/exports/preflights without browsers; `--preflight-only` checks existing fixture references without booting Laravel. Browser flags such as `--project=webkit` are forwarded. Do not substitute old exports after rebuilding.

For native checks, use [the isolated MySQL recipe](native-mysql84-acceptance.md), the new database/port above and explicit destructive-test opt-in. Run `hoa:verify-native-mysql` before tests. The verified selection is `MySqlAuditConcurrencyTest`, `MySqlPaymentConcurrencyTest`, `AuditRemediationTest`, `DuesAndPaymentManagementTest`, and `SystemAuditRegressionTest`; this is not a final full native rerun. Never target `hoa_system` with migration/refresh tests.

## Remaining risks and acceptance limits

- **External:** SEC-01 independent host/credential recovery, trusted-environment release rerun, real HOA document/signatory/editorial approvals.
- **Policy follow-up:** SC-07's application rule was approved and implemented on 23 October; see its separate verification and legacy safeguards. The 22 October test results alone do not certify that later change.
- **Operational:** nine queued jobs/zero failures observed by the baseline do not establish worker/scheduler health. No live worker, scheduler or mail delivery was exercised.
- **Coverage:** static cross-browser navigation/guidance and Livewire saves are not authenticated end-to-end browser mutations, financial approval acceptance, physical print acceptance, production-scale query measurements or a security certification. The earlier live service-request browser timeout is not closed by static tests. Dedicated PHPStan/Psalm tooling remains unconfigured.
- **Localization:** 41 inventory candidates remain classified for review in [localization coverage](localization-coverage.md); branding, language names, fixed/original document wording and the unused framework example must not be blindly translated. Complete Filipino editorial acceptance is still pending.
- **Data:** the existing household review flag is not automatically resolved. No financial/account data repair is proposed as safe to execute without separate review.
