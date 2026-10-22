# Native MySQL 8.4 acceptance recipe

Status: **locally tested on native MySQL 8.4.9**: final full suite **339 passed, 3,575 assertions, no skips** (623.39 seconds), after correcting a too-long synthetic migration-test ticket. Local MariaDB results are not used for this gate. Use a trusted isolated environment for production-release evidence; this workstation still has an unresolved incident.

## Local native binary alternative

MySQL Community Server **8.4.9** was found outside PATH at `C:/Program Files/MySQL/MySQL Server 8.4/bin`. Do not confuse it with XAMPP's `mysql` directory, which contains MariaDB. Local acceptance uses a standalone process with `--no-defaults`, an explicitly fresh `storage/framework/testing/mysql84-readiness-20261020` data directory, `--bind-address=127.0.0.1`, `--port=33184` and `--mysqlx=OFF`. This avoids changing the existing XAMPP service, `.env`, default port or database files.

Initialization used `--initialize-insecure` only for this new disposable directory; its root login is confined to the loopback test instance. Create the separately named `hoa_native_test_20261020_readiness` database and a test-only user with privileges on that database only before running the same preflight/tests below (set port 33184 instead of 33084). Do not expose this instance to other hosts, reuse its credentials elsewhere, or initialize an existing directory. Shut down this specific instance after verification; retain ignored data/logs for evidence rather than deleting unrelated databases. Starting it does not install a Windows service.

The instance's actual `SELECT VERSION()` and preflight both returned **8.4.9**. The full final result is retained in `storage/logs/readiness-native-mysql84-final.txt`; the separate 13-test document/migration/concurrency recheck is in `readiness-native-mysql84-recheck.txt`. The earlier full run's fixture failure is preserved rather than relabelled as a pass. Native results on the current workstation still require a trusted-environment rerun for production release after incident recovery.

## CI

The existing `mysql-test` job uses `mysql:8.4`. Its read-only `hoa:verify-native-mysql` preflight checks the configured and actually selected database names, destructive-test opt-in and actual server version before migrations. MariaDB, MySQL 8.0/9.x and non-test names are rejected. The preflight alone is not workflow acceptance. No workflow was remotely triggered by these source changes.

## Optional local isolation (Docker must already be installed and approved)

Use a fresh PowerShell session so test environment values cannot leak into routine application commands. Do not edit `.env` or use production credentials. Check that localhost port 33084 is unused first.

```powershell
$env:HOA_MYSQL_TEST_DATABASE = 'hoa_native_test_' + (Get-Date -Format 'yyyyMMdd_HHmmss')
docker compose -p hoa-native-acceptance -f compose.mysql84-test.yml up -d --wait
$env:APP_ENV = 'testing'
$env:DB_CONNECTION = 'mysql'
$env:DB_HOST = '127.0.0.1'
$env:DB_PORT = '33084'
$env:DB_DATABASE = $env:HOA_MYSQL_TEST_DATABASE
$env:DB_USERNAME = 'hoa_test'
$env:DB_PASSWORD = 'isolated_test_only'
$env:HOA_ALLOW_DESTRUCTIVE_TEST_DATABASE = 'true'
$env:CACHE_STORE = 'array'
$env:SESSION_DRIVER = 'array'
$env:QUEUE_CONNECTION = 'sync'
$env:MAIL_MAILER = 'array'
php artisan hoa:verify-native-mysql
```

Stop immediately if preflight fails. Only after it passes:

```powershell
php artisan migrate --force
php artisan test --compact --filter='MySqlPaymentConcurrencyTest|PhaseTwoDocumentsTest|PhaseThreeTest|DuesAndPaymentManagementTest|HomeownerProfileTest|ReleaseReadinessTest'
php artisan test --compact
git rev-parse HEAD
git diff --stat
```

Check every command's exit code before continuing. Test traits deliberately migrate/refresh the isolated test schema. Do not use this sequence against `hoa_system`. The compose service binds localhost only, has no production mounts and uses public test-only passwords; never expose it externally.

Retain server version, Git/worktree identification, exact commands, exit codes, test counts, skips, migration round-trip results and sanitized logs. Compare exact-cent financial/concurrency and populated migration outcomes with SQLite. A populated compatibility test uses synthetic data or an explicitly approved de-identified restored copy, never an unreviewed production dump.

After retaining evidence, stop/remove only this test container with `docker compose -p hoa-native-acceptance -f compose.mysql84-test.yml down`. It has no persistent volume; this destroys its disposable data. Close the test shell. Do not remove arbitrary Docker volumes or other containers. Installing Docker or a host database service is outside this implementation.
