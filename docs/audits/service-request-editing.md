# Staff/admin service-request save repair

Date: 2026-10-22 (Asia/Manila)

## Confirmed findings

Read-only diagnosis confirmed that request #1 was a Pending Cohabitation request,
assigned to an active authorized staff member, without an issued certificate.
The user confirmed attempting direct Pending -> Approved. The following were
reproduced with synthetic in-memory SQLite fixtures, not by updating that record:

1. **Workflow/UI mismatch:** every status appeared in the edit dropdown although
   the backend requires Pending -> Processing -> Approved. Direct approval rolls
   back correctly, but looks broken because of the next issue.
2. **Invisible domain validation:** errors used `status`, while the Filament form
   expects `data.status`. The edit page did not use the existing validation-mapping
   concern, so the error was absent from the rendered field.
3. **Cross-module completion conflict:** an approved certificate request could be
   manually completed without any linked certificate, hiding preparation and
   leaving no issued document.
4. **Stale successful-save UI:** the locked update action saves a different model
   instance; the form's bound record stayed stale. Once status choices depend on
   the persisted stage, the next valid step did not appear without refreshing.
   The new same-page regression reproduced this before its repair.

## Changes

- Added `ServiceRequestWorkflow` as the shared transition source for the model,
  edit action and dropdown. Current status remains selectable for ordinary edits.
  Pending allows Processing/Rejected; Processing allows Approved/Rejected.
- Certificate completion is omitted from ordinary editing and rejected by the
  transactional action. A model-level guard requires an issued, non-deleted
  certificate linked to the same request and liable homeowner before completion.
  `IssueCertificate` already persists that certificate before completing the
  request, so its existing transactional workflow remains authoritative.
- Current and retired catalog certificate types receive the same protection.
  Grandfathered non-certificate services retain authorized manual completion.
- `EditServiceRequest` uses `MapsDomainValidation` with the `data` state path,
  preserving input and showing field errors plus a danger notification for
  domain failures. Ordinary form errors remain normal Filament field errors.
- Refresh the original form-bound record only after persistence succeeds, so
  the next workflow choices and explanatory helper update without a reload.
- Added marked English/Filipino next-step guidance and localized transition
  errors without changing stored statuses. Rejection remarks are required in
  both the form and authoritative validator.

Existing authorization, actor/timestamp metadata, row locks, notifications,
original request identity, certificate preparation, original-document approvals,
historical PDFs and financial records remain in place. Ordinary authorized
metadata edits with an unchanged final status remain supported; this repair does
not introduce complaint-style finalization rules into service requests.

No migration or new dependency. No live request status changes, historical repairs,
source approvals, real account provisioning, external mail, push or deployment.
Potential older prematurely completed requests require separate read-only review;
this change does not automatically reopen or repair them.

## Verification

- Initial new edit suite: **11 passed / 1 failed**, 198 assertions. The failure
  reproduced stale form status options after a successful save; it was fixed by
  refreshing the form-bound model, not weakening the assertion.
  Log: `storage/logs/service-request-editing-initial-20261022.txt`.
- Focused edit/guidance/Phase 2 checks: **34 passed / 580 assertions**, 67.643 s.
  Includes both panels' status options, same-page Processing -> Approved saves,
  visible errors/input retention, stale direct updates, rollback, permissions,
  rejection notes, ordinary edits, grandfathered completion, audit/notification
  preservation, localized guidance and Cohabitation issuance completion.
  Log: `storage/logs/service-request-editing-focused-20261022.txt`.
- Scoped Pint, PHP syntax for all seven affected PHP files, browser-script syntax
  and whitespace checks passed. Production Vite build passed (36.94 s), retaining
  the known unrelated `/images/southville-community-hero.webp` warning.
- Full SQLite suite: **491 passed / 6 native-database-only skips / 5,567
  assertions**, 1,235.255 s; zero failures/errors. This includes certificate,
  request, payment, report, profile, authorization, notification, audit and
  localization regressions. SQLite cannot execute the six native concurrency
  tests. Evidence: `storage/logs/service-request-editing-full-20261022.txt` and
  matching `.xml` JUnit file.
- Initial Chromium live attempt: **two timeout errors / no passing cases**,
  567.416 s overall. Both cases stopped at request-list loading before editing.
  The isolated PHP server hit its 60-second limit in Composer's class loader;
  its error renderer then reported the interrupted BladeCompiler class load.
  This is recorded evidence, not proof of a missing package or a diagnosed host
  root cause. No vendor/dependency or production timeout changes were made.
  JUnit/traces: `storage/logs/service-request-editing-browser-chromium-20261022.xml`
  and `storage/framework/testing/service-request-editing-browser-chromium-20261022/`.
  The test now asserts successful list-page HTTP status and has bounded locator
  waits for clearer failures. A subsequent serial retry is tracked separately.
- The serial retry uses fresh `service-request-editing-browser-test-20261022b`
  fixtures, private compiled views and a **test-server-only**
  `php -d max_execution_time=180` flag. This does not change `php.ini`, the normal
  application server or production configuration. The original failures remain
  distinct from the retry; test-only tuning is not a production performance fix.
- Serial Chromium retry: **two navigation-timeout errors / no passing cases**,
  443.128 s overall. Both requests exceeded the bounded 90-second navigation
  wait for `/staff/service-requests`, still before the edit form. The larger
  test-only PHP budget and private view precompilation did not establish browser
  acceptance. Evidence: `storage/logs/service-request-editing-browser-retry-20261022.xml`
  and `storage/framework/testing/service-request-editing-browser-retry-20261022/`.
  Successful edit/save/refresh, responsive and Axe browser assertions were not
  reached. Firefox/WebKit were not run. Their coverage remains pending; do not
  characterize either Chromium attempt as a pass or silently omit its failures.

## Remaining risk and cleanup

The isolated request-list loading timeout needs separate profiling/reproduction;
its root cause is not established by the observed Composer timeout. The failing
browser entry point is distinct from the diagnosed direct-approval validation
bug, which was reproduced and regression-tested through Livewire. Passing PHP
tests does not prove that this browser/server issue or every workflow is fixed.

Both audit-owned servers on loopback 8031 were stopped by verified process ID and
command line. No ordinary server/database service was stopped. The two disposable
SQLite databases, private runtimes and failure diagnostics are retained for review;
they contain synthetic fixtures only. No material user data was removed.

The guarded browser seeder requires a new, specially named SQLite database under
`storage/framework/testing/service-request-editing-browser-test-*`. Its accounts,
requests, private storage, sessions, mail transport and application key are test-only.
It creates no payment, obligation, certificate or source approval. The ordinary
application database, `.env`, accounts and normal server are not reconfigured.

No native MySQL concurrency rerun is claimed for this repair. Existing independent
host/credential recovery and human document/editorial approval gates remain open.
