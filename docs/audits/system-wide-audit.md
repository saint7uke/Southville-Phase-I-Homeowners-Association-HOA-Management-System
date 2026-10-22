# System-wide audit and targeted repairs

## Original receipt PDF correction — 2026-10-21

The configured ten-slip DOCX export did not match the newly uploaded single-slip PDF. The new PDF is preserved under a separate versioned asset directory and served byte-for-byte for private source review; original-font blank overlays are remapped to its actual geometry. Historical sources/documents remain intact and no old approval is inherited. Empty monthly-receipt history now explains that only generated PDFs appear and links to the existing permission-checked receipt form. The interrupted list-page syntax error is repaired. See [the correction audit](receipt-pdf-source-correction.md) for engineering evidence and final tests; no financial repairs, live approval or schema change were performed.

Final correction evidence: **402 SQLite tests passed, five native-only skips, 4,178 assertions**; 21 focused tests and four distinct Chromium scenarios passed, with the initial pending-dialog test timeout and successful targeted retry explicitly retained. Source review matches the uploaded SHA-256, and generated-PDF raster changes are confined to original blank-field rectangles. Build/Pint/syntax/parity checks passed. Native-engine, all-engine, physical print and real HOA/security approval are not inferred from these results.

## Certificate interface follow-up — 2026-10-21

Source/signatory approval remains one-time setup, but its controls no longer dominate the certificate list. Two daily actions and one administrator-only setup dropdown replace the seven-button header; completed source approvals hide, review links remain grouped, and saved receipt viewing has no submission step. See [the interface audit](certificate-interface-simplification.md). No approval, permission or document-design safeguards were removed. Full SQLite regression: **394 passed, five native-only skips, 4,112 assertions**; focused/interface and three Chromium scenarios passed, with earlier timing/test-harness failures disclosed in the audit. No new migration or live approval was executed.

## Latest targeted continuation: registration and original monthly receipt

See [the registration/Phase/Resibo audit](registration-phase-resibo-butaw.md) and [revision evidence](shoai-revision-progress.md) for the 2026-10-21 continuation. Confirmed registration CSS/state issues are repaired; required Phase suffix and nullable historical House Number compatibility are implemented. Shared confirmed property identity now protects registration, landlord matching, households, payers and future assessments without guessing legacy suffixes or rewriting historical ledgers.

Additional reproduced safeguards preserve existing assessments: a current-period liable account cannot silently move its assessment, and an archived unresolved account's assessment requires review before another confirmed assessment is generated. The read-only identity report exposes those conflicts. No live account/financial repair was performed.

Native Word export succeeded through Windows scripting after PowerShell automation stalled. The exact preserved Resibo Butaw source is integrated into Certificates with first-slip-only overlays, private previews, payment-specific permission checks and immutable, idempotent saved PDFs. Source approval remains an actual HOA decision, not inferred from a test fixture. The latest full SQLite run passed 389 tests with five native-only skips; final native/browser/check details and interruptions are explicitly recorded in the revision checklist. This is bounded verification, not whole-system or host-security certification.

Final evidence for this increment: focused native MySQL 8.4.9 **61 passed / 379 assertions**, 18 distinct browser scenarios passed across Chromium/Firefox/WebKit (interrupted combined run plus final targeted retry), 435 PHP syntax checks passed, Pint/JavaScript/diff checks passed and production build passed. Composer advisory retry and production npm audit found no advisories. The final full-suite run was SQLite, not a full final MySQL rerun. Remaining legacy-data review, real HOA approvals, licensed font provisioning/physical print acceptance and independent host recovery remain explicit release boundaries.

Audit date: 2026-10-18 (Asia/Manila). Baseline: `4fe268d`. Local Windows/XAMPP, PHP 8.2.12; Laravel 12, Filament 4.13.0 initially. Findings below describe inspected paths and reproduced behavior, not a certification of the entire application or host.

## Localization follow-up (2026-10-20)

Confirmed report-filter regression: Filipino rendering submitted translated status values rather than canonical workflow values. The report Blade now translates labels only; a Livewire test verifies `Resolved` remains the submitted value and successfully previews the complaint report. Actual lowercase workflow-notification types now have dictionary/test coverage. Report placeholders and summary labels received targeted presentation-only localization. See `shoai-revision-progress.md` for current command results, browser diagnostics and coverage boundaries. These fixes do not close SEC-01 or constitute complete localization approval.

## Release decision: incident recovery required

Certificate workflow follow-up (2026-10-20): the separate template-management UI was removed at the user's direction. Residency now uses only the uploaded source, with source/signatory confirmation, actor-bound expiring preview, transactionally idempotent issuance and stored-PDF View/Print within the certificate resource. No live migration or actual source approval was performed. Current evidence and deployment/font prerequisites are in `original-residency-template.md` and `shoai-revision-progress.md`; earlier template-management entries describe historical checkpoints, not the current UI.

**SEC-01 is a critical, confirmed repository compromise.** `vite.config.js` contained executable obfuscated code after the normal configuration. The build configuration had already been executed before discovery during this audit (and in earlier recorded builds). Static decoding, using Python string transformations without evaluating the JavaScript, established that it:

- Exposes Node's `require` through `global.r` and uses `https.request`.
- Calls `eth_blockNumber` and `eth_getBlockByNumber` on public Ethereum RPC endpoints; scans transactions for marker `596dcc9fda875309ff85eba09cf5a97d`.
- Fetches BSC transaction input, decodes hexadecimal data, splits it on `?.?`, XOR-decodes the result, and calls `eval` on the resulting text.
- Contains RPC hostnames `ethereum-rpc.publicnode.com`, `eth.drpc.org`, `eth-mainnet.public.blastapi.io`, `rpc.mevblocker.io`, `bsc-dataseed.binance.org`, and `bsc-rpc.publicnode.com`. These are shared services used by the loader; their presence is not evidence that those service operators compromised the repository.

The loader is present in `c0d8d40` and retained in `4fe268d`; inspection of parent `6b8aaa1` did not find it. This establishes the affected history, not the identity of the person or system that introduced it. The normalized-text SHA-256 of `git show 4fe268d:vite.config.js` used for analysis was `0b68e8c4df819e271802cc389e445a76762c84fda3dcc7556bff1692ef745858`.

Containment implemented: removed the payload and unnecessary `createRequire` shim, stopped the lingering audit build process, scanned repository source and the installed Node tree for its distinctive markers, preserved the previous Node tree in ignored `node_modules.audit-quarantine-20261018`, and installed the patched lockfile with `npm ci --ignore-scripts` using a separate npm cache. Added a regression check against loader indicators. Original evidence remains in Git history; no history was rewritten.

**Unresolved:** fetched second-stage execution, outbound transfer, persistence, host integrity, and credential exposure have not been established or excluded. A clean source scan and dependency audit do not establish a clean host. Before release, investigate/rebuild the affected workstation and any build/deployment hosts from trusted media; review GitHub access/actions and affected history; revoke exposed sessions/tokens and rotate potentially accessible credentials from a trusted device (GitHub, deployment, database, SMTP, cloud/storage, SSH, and application secrets as applicable). Application-key rotation requires a deliberate encrypted-data/session recovery plan. Those external incident-response actions were not performed by this code audit. Do not reuse the quarantined dependencies.

## Findings and remediation

| ID | Severity / kind | Confirmed evidence and root cause | Remediation / state |
|---|---|---|---|
| SEC-01 | Critical / security defect | Remote-code loader in build configuration, statically decoded as above. | Source removed; host/credential incident remains a release blocker. |
| DEP-01 | High / dependency advisories | Composer reported 5 advisories across 4 packages; npm reported 4 affected packages including critical development-tool dependencies. | Compatible updates applied; fresh audits report no known advisories. Applicability details below. |
| FIN-01 | High / financial integrity | New regression reproduced an obligation remaining after an invalid legacy payment was rejected. Legacy creation occurred outside the transaction. | Wrap the complete recording workflow in a transaction; validate legacy identifiers/date; resolve the liable property account under the property mutex; reuse the property obligation; refuse a new obligation for an inapplicable schedule. |
| FIN-02 | High / business conflict | `FlagDelinquentHomeowners` sets resident status to `Delinquent`, but billing/payer queries required `Active`. Debt status stopped future billing and payment access. | Treat `Active` and `Delinquent` resident records with active user accounts as eligible; suspended/inactive/deleted accounts remain excluded. Regression covers generation and payer selection. |
| AUTH-01 | High / authorization | The liable-payer shortcut returned true before checking account/resident state. Direct proof submission by an inactive owner succeeded in the regression. Shared download middleware and report catalog also lacked active-account checks. | Check payer state before the identity shortcut, recheck eligibility inside proof transactions, enforce active accounts on shared downloads, verification-document/profile-availability endpoints and report routes/catalog/jobs/downloads. Recording/review reject inactive actors. Stale-session regressions pass. |
| REL-01 | Medium / relationship validation | The homeowner's historical `user()` relationship includes soft-deleted users; landlord lookup and new-link validation checked account status but omitted user deletion. A regression matched a deleted landlord user through its current homeowner row. | Explicitly exclude deleted users in matching and newly established links. Existing links/data are not rewritten. |
| AUTH-02 | High / permission bypass | Portal requests checked `submit_own_payment_proof`, but the shared action/Filament button checked role and household only. Regression submitted a proof after revoking that permission. | Enforce the permission in the action and hide the Filament proof button when it is absent. |
| FIN-03 | Medium / idempotency conflict | Recording with the same token/payload as a pending proof returned the pending proof as a successfully recorded payment. Unique-reference handling also preceded token recovery in races. | Distinguish recording from proof submissions; reject deleted-token replays; recheck tokens under obligation locks and recover matching tokens before reporting reference conflicts. |
| FIN-04 | High / inaccurate collections | Admin dashboard summed all payment review states, while accepted reports excluded pending/rejected proofs. | Dashboard collections use only current `Approved`/`Recorded` payments and date-only bounds. Reproduced with an accepted payment plus pending proof. |
| FIN-05 | High / financial integrity | Payment bulk delete/restore used default Filament model methods, bypassing the authorized transactional action used by individual buttons. | Both bulk processes now use `ChangePaymentDeletionState`; regression asserts reconciliation occurs inside an additional transaction. |
| FIN-06 | Medium / inconsistent household view | Tenant/co-owner dues list used the liable account, but homeowner overview summed only the viewer's own obligations and could claim the household was current. | Overview resolves the same eligible liable account. Linked-tenant balance regression added. |
| FIN-07 | Medium / premature delinquency | Partial recording on the due date reproduced `Overdue` rather than `Partial`: `isPast()` compared the date's midnight with the current time, while the overdue sweep treats the due date as inclusive. | Recording, proof approval, and delete/restore reconciliation now compare with the start of today. |
| REP-01 | Medium / date filtering | New local-timestamp fixture reproduced previous-day inclusion and selected-day late-evening exclusion. Reports converted local boundaries to UTC despite application timestamps being stored in configured local time. | Compare bounds in the configured application timezone; preserve indexable comparisons and date-only fields. No historical timestamps were rewritten. |
| REP-02 | Medium / request exhaustion and authorization feedback | Forged huge preview page numbers recursively loaded each preceding page; public page size was unbounded. Authorization exceptions were caught as generic preview failures. | Calculate/clamp the page once, allowlist sizes, validate/authorize before opening, and propagate HTTP denial responses. Regression uses a forged page/size of 100,000. |
| REP-03 | Medium / performance | Export mapping resolved a fresh report-catalog instance for every row, discarding its active-population cache and repeating announcement denominator queries. | Retain one catalog instance per export; verify row mapping does not add per-row queries. |
| REG-01 | Medium / missing verification evidence | Registration accepted a `false` storage result and saved the application with no verification document. | Require a successful path and stored file; return a field error and roll back user, role, and resident records. Mocked storage-failure regression added. |
| DUES-01 | Medium / concurrency risk confirmed by code | Generator attempted to lock a property registry row without creating it; newly edited/admin-created addresses could have no mutex row. | Insert the registry key before locking it. Existing property and unique-obligation checks retained. |
| UI-01 | Medium / broken assets | Both tracked hero images had been deleted while CSS/React still referenced them. | Restore original image bytes from the parent commit. Runtime requests must be verified separately from Vite's public-asset warning. |
| QA-01 | Low / layout regression | Existing test expected final full-width complaint chart; account widget ordering placed it earlier. | Restore the chart as the final widget with explicit sort order. |
| QA-02 | Low / test defects | Report fixture expected a `Pending` complaint under `Resolved` filtering; money assertions depended on SQLite's unpadded SUM strings; favicon test expected HTTP 200 from intentional redirects. | Correct the fixture, assert exact minor units across engines, follow legacy redirects and check destination branding. The fixture failure alone was not evidence of a timezone bug. |
| TOOL-01 | Low / tooling | Browser panel tests targeted obsolete login button text; default PHP executable referenced another workstation's `F:` drive. Pint reported imports/spacing plus attempted to format the third-party Composer installer. | Use the submit control/current PHP on PATH, retain explicit binary override, fix project formatting, and exclude the upstream installer from Pint. |
| DB-01 | Medium / migration defect | Real MySQL rollback raised error 1553: certificate uniqueness index was needed by the service-request foreign key after InnoDB replaced its implicit index. | Restore the non-unique supporting index before dropping uniqueness in that migration's `down()`. This changes rollback behavior only; no current application schema/data was changed. |
| DB-02 | Medium / migration defect | Continuing MySQL rollback reproduced error 1553 on the payment obligation/date index while its foreign key still existed. | Drop the foreign key before its supporting index and column. Exercised only on the disposable database. |
| PAY-EDIT-01 | Medium / stale authorization | Metadata action authorized the caller's old payment model but did not check the locked, current review state. It also mislabeled every database failure as a duplicate reference. | Reauthorize the locked payment; translate only actual reference collisions to field errors. |
| CERT-01 | Medium / file/data integrity | Generated certificate file path was recorded for cleanup only after the certificate update returned; an update failure could orphan the PDF. Approved request descriptions also exceed the certificate purpose column's 255-character limit. | Remove the generated file on failed path persistence; validate explicit purpose length and bound the default description excerpt to 255 characters while retaining the full request text. |

### Dependency applicability

- Filament packages `4.13.0 → 4.13.4`: GHSA-7m6h-rg42-m449 / CVE-2026-104181 concerns MFA-management password reauthentication. MFA is not configured in the inspected panels; vulnerable dependency presence was confirmed, exploitation was not.
- CommonMark `2.10.1 → 2.10.4`: GHSA-97jj-33gv-5xf9 and GHSA-3q6v-r5mr-hxv8 concern raw-HTML filtering and table-parser denial of service. Application views render resident text using escaped output; the audit did not establish an untrusted Markdown exploit path.
- Symfony HttpFoundation `7.4.18 → 7.4.21`: CVE-2026-108082 concerns Cache-Control parsing denial of service and is relevant to the HTTP stack.
- Symfony YAML `7.4.15 → 7.4.21`: CVE-2026-109413 concerns unbounded YAML inline recursion; no public YAML-upload/parser endpoint was identified.
- Axios `1.19.0 → 1.20.0`: npm reported prototype-pollution gadgets, proxy/redirect issues and denial-of-service advisories. Browser use and development installation were identified; Node HTTP/proxy exploit paths were not demonstrated.
- `concurrently 9.2.4 → 9.2.5`, `shell-quote 1.9.0 → 1.12.0`, `source-map-js 1.2.1 → 1.2.2`: command-injection/source-map denial-of-service advisories affect development tooling. No public production endpoint invoking these tools was identified.

Sources are the actual Composer/npm advisory output obtained during the audit; patched versions remain within existing manifest constraints. Dependency version updates do not remediate SEC-01 host exposure.

## Coverage and authorization matrix

Source inventory at handoff: 27 domain actions, 31 controllers, 17 models, 13 policies, 9 middleware classes, 103 Filament files, 2 jobs, 10 notifications, 5 console files, 33 migrations, 3 route files, and 34 Blade views. There is no standalone `app/Livewire` directory; interactive components are under Filament. Inventory counts identify the reviewed surface, not proof that every branch was exercised.

| Area | Inspection and exercised coverage | Limits |
|---|---|---|
| Authentication / accounts | Guards, panel access, pending/rejected/suspended/inactive login, approval verification, email changes/reservations, password reset/change and session revocation; lifecycle and panel tests. | External mail delivery and every concurrent account-state race not verified. |
| Registration / profiles / relationships | Shared validators, normalized property/name/email rules, reservation lookup, landlord uniqueness, tenant linking, immutable phase/occupancy, file rollback, private photo delivery. | Legacy unmatched relationships are review observations; no accounts rewritten. |
| Dues / payments | Schedule applicability/overlap, property liability, partial/exact-cent payments, proofs/reservations, reviews, token retries, receipts, ledger deletion/restoration and active/delinquent household access. | MySQL fixture tests are not a production-volume benchmark. Historical relocation lacks a property snapshot; see risks. |
| Complaints / requests | Creation actions, current resident/assignee validation, private attachments, status transitions, assigned-staff operational fields, finalization and audited reopening. | External notifications exercised through fakes/array mail, not real recipients. |
| Certificates / catalog | Admin custom-type governance, staff use, normalized uniqueness, approved-request issuance, numbering, expiry, private signed downloads and revocation. | Visual verification of every PDF variant is not exhaustive. |
| Announcements / contact | Publication/audience/expiry filters, unique readership/current active reach, escaped content and public inquiry validation. | Historical audience sizes cannot be reconstructed from existing schema. |
| Reporting / audit | All 13 definitions, current/archive scopes, staff restrictions, preview pagination, accepted/review totals, dates/search/sorts, CSV/XLSX/PDF, private queued storage/retry/ownership/expiry, immutable/redacted audit records. | Dedicated PHPStan/Psalm analysis is not configured. |
| Infrastructure / frontend | Route/middleware inventory, all PHP-source syntax, private/public disks, build scripts, lockfiles, job retry/after-commit behavior, scheduler definitions and browser fixtures. | No production deployment, live queue worker, remote storage, Reverb delivery or OS security certification. |

| Workflow | Administrator | Staff | Owner / eligible Co-owner / linked Tenant |
|---|---|---|---|
| Account approval / role governance | Existing administrative permissions | Denied | Denied |
| Complaint creation for another resident | Allowed | Denied | Denied; own submissions only |
| Open complaint editing / resolution | Allowed under transitions | Current assignee only; operational fields | Read own complaint |
| Reopen resolved complaint | Allowed with reason | Current assignee with reason | Denied |
| Rejected / Closed / Dismissed complaint editing | Denied | Denied | Denied |
| Dues schedules / payment recording and proof review | Existing permissions | Existing manage permissions | Own household proof submission only |
| Tenant relationship management | Existing manage permission | Existing manage permission | Own linked tenants read-only |
| Custom certificate type creation | Allowed | Denied; existing catalog use allowed | Request active types only |
| Reports | All permitted reports/archive scopes | Operational subset, current only | Denied |
| Private files | Authorized operational access | Authorized operational access | Own records/eligible payment records only |

Inactive/deleted accounts are denied on the hardened shared routes. These summaries do not replace per-record policies or authorize new role privileges.

## Data inspection and repair policy

`php artisan hoa:audit-data --json` performs read-only aggregate queries. Exit 1 signals a nonzero integrity check; informational observations require review but do not themselves fail the command. It checks invalid obligation balances, accepted-payment reconciliation, excess reservations, payment/obligation identity, current residents with missing/deleted users, invalid landlord links, legacy unallocated payments, unlinked tenants, duplicate property billing groups, equivalent email groups, and overlapping schedules.

Local `hoa_system` inspection: 3 users, 1 homeowner, 1 announcement; 0 payments, obligations, complaints, service requests and certificates. All implemented check and observation counts were zero. This sparse dataset provides no evidence about a populated production ledger. All existing financial/account data remained read-only; there is no proposed existing-record repair in this environment. Nonzero findings elsewhere must be exported for review before any repair.

No new schema migration is required by these fixes. Existing migrations were exercised on disposable databases. Never run `migrate:fresh`, migration rollback, or destructive PHPUnit tests against `hoa_system`.

## Verification log

- Baseline SQLite suite: **267 passed, 3 failed** (report fixture, dashboard ordering, legacy favicon redirects).
- Baseline Pint: failed on project imports/spacing and third-party installer formatting; Composer manifest valid.
- Baseline security audits: Composer 5 advisories/4 packages; npm 4 affected packages (2 high, 2 critical package classifications).
- Four initial payment reproductions failed before fixes (orphan obligation, inactive liable-payer shortcut, inactive direct proof submission, proof-token replay into recording); passed after fixes.
- Local-timestamp boundary reproduction failed before the date fix: expected selected-day midnight/end-of-day, received previous-day end/selected-day midnight.
- Intermediate complete SQLite run after principal fixes: **283 passed**; final results recorded below after remaining checks finish.
- Initial isolated MySQL selection: **61 passed, 1 failed**; failure was SQLite-specific decimal string formatting in a test, subsequently replaced with minor-unit assertions.
- PHP syntax: **360 files parsed** in app/bootstrap/config/database/routes/tests at that checkpoint.
- Post-update Composer and npm audits: **zero known advisories**. Composer validation passed.
- Clean-source production Vite build passed. It retains a public-image resolution warning; the image files are restored and runtime verification is tracked separately.
- Disposable MySQL database: `hoa_audit_test_20261018_1640`. Browser database: ignored `database/hoa-audit-browser-20261018.sqlite`, served only on localhost port 8012 with array mail and isolated seeded accounts.

### Final verification evidence

- Complete SQLite suite after the principal repairs and dependency updates: **292 passed, 2 skipped, 1,366 assertions** (359.18 seconds). The skipped cases require MySQL and were run separately below. Three subsequently added regression methods (deleted landlord user, revoked proof-submission permission, inclusive due-date handling) were each reproduced and verified separately; the final focused run is recorded below rather than representing the earlier full run as covering later additions.
- Final related-module SQLite run after all source changes: **69 passed, 294 assertions** (141.68 seconds), covering all 22 new system-audit regressions plus dues, payments, completion/ledger, landlord/tenant, reporting/export-volume and build-integrity tests. No failures remained in that selection. The earlier full-suite count and this overlapping selection must not be added together as a count of distinct tests.
- Disposable SQL-driver regression selection: **29 passed, 152 assertions**. This includes two real multi-process payment races: competing payments cannot overdraw an obligation, and matching concurrent tokens return one payment. Migration rollback exposed DB-01/DB-02; both were repaired and the rerun passed.
- Additional disposable SQL-driver relationship/permission selection: **4 passed, 7 assertions**. Extended due-date regression (recording, proof approval, delete, restore): **1 passed, 4 assertions** on each of SQLite and the SQL driver.
- The local SQL server is **MariaDB 10.4.32 via Laravel's MySQL driver**, not native MySQL 8.4. Native MySQL 8.4 remains a CI/environment verification requirement; the existing CI configuration targets it, but that remote job was not executed here.
- Final complete Chromium browser run: **20 passed** (6.6 minutes), including representative mobile/desktop registration statuses, profile validation, reports, three role panels, keyboard navigation, responsive layouts and configured Axe checks. An earlier run had 18 passes and two failures: a transient-state assertion using a fixed delay, and a report timeout during concurrent heavy checks. The status test now explicitly controls response timing; isolated retries passed, followed by the complete passing run. Firefox/WebKit and every individual form variation were not tested.
- Both restored public hero-image URLs returned HTTP 200 from the isolated browser server. Clean production build passed; its remaining public-asset resolution warning did not correspond to a missing runtime image.
- Final PHP syntax parsing: **362 files passed**. `git diff --check` and final `php vendor/bin/pint --test` passed. Project Pint formatting was applied; its third-party installer exclusion avoids modifying upstream tooling.
- General test filesystem roots now use per-process directories under `storage/framework/testing`; tests without explicit `Storage::fake()` cannot place replacement documents in real application disks.
- Report-volume checks passed for 1,005-row CSV export across a chunk boundary and the 2,000-row PDF limit, including the readable Livewire warning. Announcement row mapping has a query-count regression against repeated population queries. This is targeted evidence, not a production-load benchmark; no speculative indexes were added.
- Queued export formats are explicitly validated rather than silently falling back to CSV. A configuration-integrity regression runs before Node installation/build in CI; it checks known loader indicators and does not replace supply-chain review.
- No production mail was sent. The audit-owned localhost browser server was stopped after testing. Disposable databases, ignored test artifacts, and the quarantined dependency tree are retained locally; no user database or historical records were deleted.

## Remaining risks and operating notes

1. **SEC-01 host/credential exposure remains critical and blocks release.** No endpoint scan or passing test can establish that fetched code did not run. CI caches/artifacts and machines that built affected commits need independent review. Do not force-push away the evidence.
2. Historical obligation/property identity is derived from the current homeowner address. The schema does not snapshot a stable property ID/address per obligation; relocating a liable resident after billing can make historic property labels/grouping ambiguous. Preserve history and design an additive snapshot/backfill policy with reviewed evidence before changing existing records.
3. Mutexes protect inspected application workflows; ad-hoc SQL/imports/model writes can bypass shared address/schedule validation. Multi-process payment races are tested separately; every address/schedule/tenant reassignment race has not been reproduced.
4. Confirmation of append-only audit behavior is at application/model level. A privileged database operator can bypass it; database-level tamper resistance and off-host retention are outside current implementation.
5. Mail, cloud storage, worker supervision, scheduled execution, Reverb and disaster recovery require environment-specific acceptance. Scheduler definitions alone do not prove a scheduler is running.
6. Browser tests use representative fixtures, not every data combination. Browser/network checks executed on this workstation do not establish host trust after SEC-01.
7. Installer distributions `composer.phar` and `composer-setup.php` remain tracked local tooling. The installer is excluded from formatting; no conclusion about workstation compromise depends on merely finding these files.

Changes are intended for an ordinary reviewed follow-up commit. No push, deployment, external notification, credential rotation or live-data repair was performed.

## Follow-up: phased revision, 2026-10-20

The first staged revision repairs profile phone-state injection and a legacy missing-phase save blocker, introduces Cash-only new recording, disables new electronic proofs while preserving review/history, and adds an authorized current-month dues recovery action using the existing generator. Payment method/reference metadata is now immutable. Published Filament assets have been synchronized with installed packages.

Phase 2–3 source increments now add unified role-selected authentication and secure invitations; homeowner-only complaint creation; restricted new certificate types, grandfathered requests, versioned approval-gated templates and snapshots; provisional household/RBI management and account-only membership moves; private published-project images; and persistent English/Filipino locale infrastructure. Browser regression testing additionally corrected locale CSRF handling. The full SQLite checkpoint passed **321 tests, two SQL-only skips, 1,523 assertions**; the expanded targeted selection passed **63 tests, 423 assertions**. The SQL service is available again: **32 tests, 219 assertions** passed on a new disposable **MariaDB 10.4.32** database, including both payment concurrency scenarios. This is not native MySQL 8.4 verification.

See [the phased revision checklist](shoai-revision-progress.md) for exact commands, migration/backfill safeguards, later checks and remaining coverage. Complete human-reviewed translation coverage, wider browser acceptance, native MySQL 8.4 and independent incident recovery remain explicit gates/work items. No live-data migration/repair, officer provisioning, external notification, push or deployment is included.

Final delivery checkpoint: **328 passed, two SQL-only skips, 1,561 assertions** on SQLite, with later modal/private-draft checks also passing. Two separate Chromium runs passed **four workflows**, including actual profile persistence, responsive/Axe checks and no-remount language switching with unsaved React input and a temporary photo upload. The runtime upload check used normal CSRF enforcement against the isolated database. The production build, PHP syntax, formatting and whitespace checks passed at the documented checkpoints. This is bounded verification, not a certification that every application behavior or deployment environment has been tested.

## Localization / release-readiness continuation — 2026-10-20

Shared dictionaries, presentation-only form/status translations, notification placeholders, live validation feedback and source inventory have been expanded. Browser checks corrected the Filament 4 field-label selector and feedback restoration after unrelated Livewire morphs. Contact submission retained React's form reference across asynchronous work and uses the existing session/CSRF-aware browser route; a real successful save no longer becomes a false failure on form reset.

Native MySQL Community Server **8.4.9** was found outside PATH and tested through a separate loopback instance/data directory/database, not XAMPP's MariaDB or `hoa_system`. Its first full run passed 338 tests and exposed one test-only 39-character ticket fixture violating the real 32-character schema limit. The synthetic identifier was shortened without changing schema/data policy. Native document/migration/concurrency recheck passed **13 tests, 70 assertions**; final full-run results belong in the revision checklist, not an inferred pass here.

Firefox and WebKit each passed four isolated payment/access/household scenarios. These do not establish every workflow or whole-product accessibility. The authoritative ongoing results, failures/retries, coverage boundaries and evidence paths are in `shoai-revision-progress.md` and `localization-coverage.md`.

HOA approval and independent host/credential recovery are still open. Review packages document responsible evidence and sign-off, not substitute approvals. Native tests on the current workstation do not establish host trust. No `.env` change, live migration/data repair, external notification, credential rotation, push or deployment was authorized or performed in this continuation.

Final native MySQL **8.4.9** full rerun passed **339 tests, 3,575 assertions, no skips** (623.39 seconds). Wider browser evidence covers nine Chromium scenarios across a combined run and documented targeted setup retry, plus four Firefox and four WebKit scenarios. The combined Chromium run was **8 passed / 1 fixture-setup failure**, not an unconditional green run; the corrected payment setup retry passed. See the revision checklist for exact scope and limitations. Temporary test servers were stopped; disposable evidence/data was retained without touching the live database or existing XAMPP service.

## Focused follow-up: Cohabitation request guidance (2026-10-22)

Confirmed and fixed an advertised Cohabitation draft-preview action that always
returned HTTP 409. Approved managers now enter existing certificate preparation;
other authorized viewers receive localized status guidance. Strict homeowner
request-list lazy loading was also reproduced and repaired with eager loading.
Full SQLite checkpoint: **479 passed, six SQL-only skips, 5,336 assertions**;
no failures/errors. This does not supersede native MySQL/security/HOA release
gates or imply complete live-browser coverage. See
[the focused audit](cohabitation-request-guidance.md) for evidence and limitations.
No live-data/source-approval changes, migration, notification, push or deployment.

## Focused follow-up: service-request editing (2026-10-22)

Confirmed medium-severity workflow defects: the dropdown offered transitions
rejected by the model, domain errors were not mapped to Filament field paths,
certificate requests could be completed without a certificate, and successful
locked updates left the form-bound record stale. Shared transition rules,
mapped field errors, issuance-controlled completion and post-save model refresh
repair these conflicts without changing existing permissions or original sources.
Focused checkpoint: **34 passed / 580 assertions**. Full/browser results and
limitations are tracked in [the save-workflow audit](service-request-editing.md).
No live request was advanced or repaired; no migration or source approval.

Final SQLite result for the edit repair: **491 passed / six native-only skips /
5,567 assertions**, no failures/errors. Initial and serial Chromium attempts each
had two timeout errors at request-list loading before editing. Browser acceptance
remains blocked, with root cause unconfirmed; no responsive/Axe/save-browser pass
or native concurrency rerun is claimed. Owned test servers were stopped and
synthetic diagnostics retained. See the focused audit for exact evidence.
