# Local readiness-gap verification

This runner closes five environment-dependent checks and the outdated request-email expectation. It is not a full-system certification or a production deployment command.

## Commands and prerequisites

From the repository root on Windows:

```powershell
node scripts/run-readiness-gap-checks.mjs --sqlite-only
node scripts/run-readiness-gap-checks.mjs --mysql-only
npm.cmd run test:tooling
php vendor/bin/pint --test tests/Feature/AdminServiceRequestCreationTest.php tests/Feature/ServiceRequestMailEligibilityTest.php tests/Feature/MySqlTenantPropertyPaymentsTest.php scripts/check-readiness-prerequisites.php
```

Omit the mode flag to run both groups sequentially. PHP with PDO SQLite/MySQL and Node are required. The MariaDB launcher uses `C:/xampp/mysql/bin` by default; `HOA_TEST_MYSQL_BIN` may select another local binary directory containing `mysql.exe`, `mysqladmin.exe`, `mysqld.exe` and `mysql_install_db.exe`. No existing database server or credentials are used.

The existing licensed Windows `C:/Windows/Fonts/arial.ttf` is selected explicitly for Cohabitation. `HOA_TEST_ARIAL_FONT` can select another readable, licensed copy of the genuine Arial font, subject to the application's identity validation. Residency uses the existing private `storage/app/private/fonts/timesbd.ttf`; `HOA_TEST_RESIDENCY_FONT` can select the verified licensed local file. No font is copied, uploaded or substituted. These overrides are child-process environment variables; `.env` remains unchanged.

The preflight registers the genuine Cohabitation font through `ResidencyFont`, exercising its identity, checksum, conversion lock and private-cache handling. The existing tests use synthetic sources and isolated storage. Their test-only source confirmations do not approve any official HOA source. Renderer, overflow, preview ownership and issuance protections are unchanged.

## Database and notification safety

- SQLite runs in memory with a temporary, unused configuration-cache path and ephemeral application key. Mail uses the array transport; SMS is disabled. Notification/job assertions use fakes.
- MariaDB starts from a fresh private temporary data directory, on a dynamically allocated port bound to `127.0.0.1`. No Windows service is installed.
- Database names must match `hoa_certificate_dues_tests_gap_<12 hexadecimal characters>`. The runner checks loopback, port, exact naming and explicit destructive-test opt-in before creating the schema.
- A random-password test user receives privileges only on that disposable schema. The existing `tests/bootstrap.php` destructive-database guard remains active. SQLite skip guards are retained rather than removed.
- The temporary server is shut down in `finally`. Synthetic database files, JUnit XML, safe server logs and `report.json` remain in the printed `hoa-readiness-gaps-*` temporary directory for inspection. They are not deployment artifacts. Output is buffered while each PHPUnit command runs.
- There is no SSH, production environment edit, live email/SMS, global worker, scheduler or historical-job replay.

## Exact formerly skipped cases

| Environment | Test |
| --- | --- |
| SQLite + genuine Arial | `IntegratedResidencyCertificatesTest::test_existing_cohabitation_approval_remains_inside_the_certificate_resource` |
| SQLite + genuine Arial | `ServiceRequestEditingTest::test_cohabitation_issuance_still_completes_the_approved_request_once` |
| Disposable MariaDB | `MySqlTenantPropertyPaymentsTest::test_recording_rechecks_relationships_committed_while_waiting_for_the_property`, data set `unlinked tenant` |
| Disposable MariaDB | Same method, data set `inactive landlord account` |
| Disposable MariaDB | `MySqlTenantPropertyPaymentsTest::test_concurrent_cash_payments_by_a_co_owner_linked_tenant_cannot_overallocate` |

The MariaDB group additionally runs all three `MySqlCertificateDuesConcurrencyTest` cases. The SQLite group includes request authorization/attachments, approval/issuance, preview security, dues eligibility, tenant payments, email reliability and branding regressions.

## Corrected email coverage

`AdminServiceRequestCreationTest` still proves that an administrator can create a request for eligible current resident records whose accounts are Inactive, preserving attachment, audit, transaction and authorization coverage. Its UI assertion now checks durable queued intent, not synchronous transport delivery.

`ServiceRequestMailEligibilityTest` explicitly invokes `DeliverWorkflowMail` twice for each delivery identity. Active verified homeowners receive exactly one intended notification; Inactive, Pending, Suspended, Rejected, unverified and subsequently deleted accounts are suppressed. Eligible Staff operational alerts remain covered, with no resident workflow message sent to the administrator or an unrelated homeowner. Committed intents dispatch after commit; rolled-back requests leave no intent/job. Repeated event production reuses its delivery identity.

These are fake-delivery assertions. An `Accepted` result in these tests is not evidence of SMTP acceptance or actual inbox receipt. No mailbox or email-client rendering check is performed by this runner.

## Results — 2026-11-10

- SQLite: **187 tests, 1,501 assertions passed, zero skips**, including both Cohabitation cases.
- Tooling: **16 tests passed**, including loopback/database-name/opt-in and runner-argument guards.
- Pint passed for the changed PHP test/helper files.
- Disposable MariaDB 10.4.32: **six tests, 38 assertions passed, zero skips**, including all three formerly skipped tenant cases and all three certificate-dues concurrency cases.
- The initial two MariaDB attempts each had five passes and one readiness timeout (different relationship data sets). Inspection confirmed the worker was already waiting on the property mutex while its parent still awaited READY. Symfony's initial Windows process-status read can consume output before the `waitUntil` callback sees it. The test now checks accumulated output with a 20-second readiness deadline and retains the running-worker, stale-relationship and no-overallocation assertions. The patched six-case run passed in 58.278 seconds. No runtime defect or application change was required.
- Evidence: SQLite JUnit XML and `report.json` in `C:/Users/bargo/AppData/Local/Temp/hoa-readiness-gaps-RsmVnn`; passing MariaDB evidence in `C:/Users/bargo/AppData/Local/Temp/hoa-readiness-gaps-F6t79Y`. The latter records a private loopback instance and zero failures/errors/skips; its server log records normal shutdown. These temporary artifacts are retained locally, not committed.
- Across the two non-overlapping final runner groups: **193 tests, 1,539 assertions passed**. Earlier targeted runs overlap these groups and are not added to that total. No intended-environment case was skipped. Generic SQLite runs still correctly skip MariaDB-only tests.

The production application and notification policy are unchanged. Runtime deployment is not required for these test/runner/documentation changes.
