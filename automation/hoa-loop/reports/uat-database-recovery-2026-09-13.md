# UAT database recovery and PHPUnit isolation correction

Date: 2026-09-13  
Environment: Local XAMPP 8.2.12, synthetic hoa_system_uat data only

## Incident

A direct php artisan test invocation ran while bootstrap/cache/config.php contained the local MySQL candidate connection. Laravel loaded that cache before PHPUnit's DB_CONNECTION=sqlite and DB_DATABASE=:memory: variables could take effect. RefreshDatabase therefore began rebuilding hoa_system_uat. The run was interrupted while MariaDB was unavailable, leaving only the first migration and no fixture users.

No production system or production data was involved. The older incompatible hoa_system database and Laravel storage files were not changed.

## Recovery

- Verified the retained archive SHA-256 as 63bfe8445fb1abc0f6fb0635a4fd28a66e1c6246ff146dc42c1af9f15e1e47fc.
- Dropped and recreated only the damaged synthetic hoa_system_uat schema under the existing rebuild/import authorization.
- Imported the archive's hoa_system_uat.sql.
- Confirmed all 22 archived migrations and the archived 12-table row-count fingerprint.
- Re-ran the production-refusing, idempotent UatSeeder.
- Confirmed all six UAT identities, lifecycle states, and role mappings.
- Confirmed /up and / return HTTP 200.
- Passed the live Chromium Admin, Staff, and Homeowner matrix 3/3.
- Removed only the temporary extraction directory; retained the verified ZIP.

## Prevention

phpunit.xml now sets APP_CONFIG_CACHE=bootstrap/cache/config-testing.php, a test-only path that is absent by default. PHPUnit therefore loads its in-memory SQLite configuration even when a local or production-style bootstrap/cache/config.php exists.

ApplicationConfigurationTest proves the testing environment and an explicitly isolated database. PHPUnit bootstrap rejects every non-SQLite database unless HOA_ALLOW_DESTRUCTIVE_TEST_DATABASE=true and its name contains test or tests. With the MySQL cache still present, an attempted hoa_system_uat boot was rejected before Laravel started.

The database-heavy tests now consistently use RefreshDatabase transactions instead of mixing open transactions with per-class table drops. A complete disposable XAMPP MariaDB/MySQL-compatible run found that the required Special Assessment value exceeded the original 16-character frequency column. Migration 2026_09_13_000001 widens that column to 32 characters without a destructive rollback. The focused Dues suite passed 11 tests / 44 assertions and the complete disposable database suite passed 134 tests / 534 assertions. The scratch schema was removed, the migration was applied to UAT, and UAT now has 23 migrations and six fixtures.

The final protected SQLite quick verifier passed Pint and 134 tests / 533 assertions. The one-assertion difference is the database-specific isolation branch: SQLite proves :memory:, while the non-SQLite lane proves the destructive opt-in and disposable database naming invariant.

The /up readiness route now handles Laravel's built-in DiagnosingHealth event with select 1. It returns success only when the configured database is readable. Regression coverage proves both the healthy response and a forced database-outage 500 response.

## Remaining release meaning

This restores the synthetic UAT candidate and strengthens test/monitoring safety. It does not replace representative human UAT, production-format backup approval, screen-reader/zoom/document-print review, production configuration approval, or deployment authorization.
