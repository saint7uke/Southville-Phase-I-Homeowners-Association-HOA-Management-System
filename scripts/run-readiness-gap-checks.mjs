import { spawn, spawnSync } from 'node:child_process';
import { mkdtemp, readFile, access, stat, writeFile } from 'node:fs/promises';
import { createWriteStream } from 'node:fs';
import { constants } from 'node:fs';
import { randomBytes } from 'node:crypto';
import { createServer } from 'node:net';
import path from 'node:path';
import os from 'node:os';
import { fileURLToPath } from 'node:url';
import { assertReadinessArguments, assertReadinessDatabaseTarget } from './lib/readiness-test-guards.mjs';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const args = process.argv.slice(2);
const settlementsOnly = args.includes('--payment-settlements');
assertReadinessArguments(args);
const directory = await mkdtemp(path.join(os.tmpdir(), 'hoa-readiness-gaps-'));
const secrets = [];
const scrub = value => secrets.reduce((text, secret) => text.replaceAll(secret, '[redacted]'), String(value));
const report = { directory, productionAccessed: false, messagesSent: false, results: [] };
const environment = { ...process.env, APP_ENV: 'testing', APP_DEBUG: 'false',
    APP_CONFIG_CACHE: path.join(directory, 'unused-config.php'), APP_KEY: `base64:${randomBytes(32).toString('base64')}`,
    DB_CONNECTION: 'sqlite', DB_DATABASE: ':memory:', DB_URL: '', DB_USERNAME: 'synthetic', DB_PASSWORD: 'synthetic-unused',
    MAIL_MAILER: 'array', QUEUE_CONNECTION: 'sync', CACHE_STORE: 'array', SESSION_DRIVER: 'array', BCRYPT_ROUNDS: '4',
    TEXTBEE_ENABLED: 'false', TEXTBEE_TEST_MODE: 'true', TEXTBEE_TEST_NUMBERS: '', PULSE_ENABLED: 'false',
    TELESCOPE_ENABLED: 'false', NIGHTWATCH_ENABLED: 'false', HOA_ALLOW_DESTRUCTIVE_TEST_DATABASE: 'false',
};
const font = process.env.HOA_TEST_ARIAL_FONT || (process.platform === 'win32' ? 'C:/Windows/Fonts/arial.ttf' : '');
if (!font || !(await stat(font)).isFile()) throw new Error('Supply a readable licensed Arial TTF using HOA_TEST_ARIAL_FONT. No substitute font is accepted.');
await access(font, constants.R_OK);
environment.CERTIFICATE_COHABITATION_FONT = path.resolve(font);
const bold = process.env.HOA_TEST_RESIDENCY_FONT || path.join(root, 'storage/app/private/fonts/timesbd.ttf');
await access(bold, constants.R_OK);
environment.CERTIFICATE_RESIDENCY_FONT = path.resolve(bold);

function run(binary, parameters, env = environment, input) {
    const result = spawnSync(binary, parameters, { cwd: root, env, input, encoding: 'utf8', windowsHide: true,
        maxBuffer: 16 * 1024 * 1024, timeout: 600_000 });
    if (result.stdout) process.stdout.write(scrub(result.stdout));
    if (result.stderr) process.stderr.write(scrub(result.stderr));
    if (result.error || result.status !== 0) throw new Error(`Local verification failed (${path.basename(binary)}, exit ${result.status ?? 'unavailable'}).`);
}
async function tests(name, filter, env) {
    const junit = path.join(directory, `${name}.xml`);
    run('php', ['vendor/phpunit/phpunit/phpunit', '--filter', filter, '--testdox', '--display-skipped', '--fail-on-skipped', '--log-junit', junit], env);
    const xml = await readFile(junit, 'utf8');
    const suite = xml.match(/<testsuite\s[^>]+>/)?.[0] || '';
    const count = key => Number(suite.match(new RegExp(`\\b${key}="(\\d+)"`))?.[1] ?? NaN);
    const result = { name, tests: count('tests'), assertions: count('assertions'), failures: count('failures'), errors: count('errors'), skipped: count('skipped') };
    if (!Number.isFinite(result.tests) || !Number.isFinite(result.skipped) || result.skipped !== 0) throw new Error('Missing or skipped intended-environment test coverage.');
    report.results.push(result);
}
async function freePort() {
    const server = createServer();
    await new Promise(resolve => server.listen(0, '127.0.0.1', resolve));
    const port = server.address().port;
    await new Promise(resolve => server.close(resolve));
    return port;
}

let server;
let mysql;
let mysqladmin;
let port;
try {
    console.log(`Local-only readiness artifacts: ${directory}`);
    run('php', ['scripts/check-readiness-prerequisites.php']);
    if (!args.includes('--mysql-only') && !settlementsOnly) {
        const names = ['AdminServiceRequestCreationTest', 'ServiceRequestMailEligibilityTest', 'CertificateDuesEligibilityTest',
            'DuesWorkflowTest', 'ServiceRequestEditingTest', 'ServiceRequestPreviewApprovalTest', 'CertificateWorkflowTest',
            'IntegratedResidencyCertificatesTest', 'TenantPropertyPaymentsTest', 'EmailReliabilityTest', 'EmailBrandingTest',
            'UiReliabilityTest', 'WorkflowNotificationTest'];
        await tests('sqlite-certificates-email-payments', `^(?!.*MySql).*(${names.join('|')})`, environment);
    }
    if (!args.includes('--sqlite-only')) {
        if (process.platform !== 'win32') throw new Error('This disposable MariaDB launcher currently supports Windows only; no remote database fallback is permitted.');
        const bin = process.env.HOA_TEST_MYSQL_BIN || 'C:/xampp/mysql/bin';
        mysql = path.join(bin, 'mysql.exe'); mysqladmin = path.join(bin, 'mysqladmin.exe');
        for (const file of [mysql, mysqladmin, path.join(bin, 'mysqld.exe'), path.join(bin, 'mysql_install_db.exe')]) await access(file);
        const datadir = path.join(directory, 'mariadb');
        port = await freePort();
        run(path.join(bin, 'mysql_install_db.exe'), [`--datadir=${datadir}`, `--port=${port}`, '--silent']);
        const output = createWriteStream(path.join(directory, 'mariadb-server.log'));
        server = spawn(path.join(bin, 'mysqld.exe'), [`--defaults-file=${path.join(datadir, 'my.ini')}`, '--console', '--bind-address=127.0.0.1', `--port=${port}`, `--datadir=${datadir}`],
            { env: environment, windowsHide: true, stdio: ['ignore', 'pipe', 'pipe'] });
        server.stdout.pipe(output, { end: false }); server.stderr.pipe(output, { end: false });
        let ready = false;
        for (let attempt = 0; attempt < 80; attempt++) {
            if (server.exitCode !== null) throw new Error('Disposable MariaDB stopped during startup.');
            const probe = spawnSync(mysqladmin, ['--host=127.0.0.1', `--port=${port}`, '--user=root', '--connect-timeout=1', 'ping'], { encoding: 'utf8', windowsHide: true, timeout: 2000 });
            if (probe.status === 0) { ready = true; break; }
            await new Promise(resolve => setTimeout(resolve, 250));
        }
        if (!ready) throw new Error('Disposable loopback MariaDB did not become ready.');
        const database = `hoa_certificate_dues_tests_gap_${randomBytes(6).toString('hex')}`;
        const password = randomBytes(24).toString('hex'); secrets.push(password);
        assertReadinessDatabaseTarget({ host: '127.0.0.1', port, database, destructiveOptIn: true });
        const sql = `CREATE DATABASE \`${database}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; CREATE USER 'hoa_gap_test'@'127.0.0.1' IDENTIFIED BY '${password}'; GRANT ALL PRIVILEGES ON \`${database}\`.* TO 'hoa_gap_test'@'127.0.0.1';`;
        run(mysql, ['--host=127.0.0.1', `--port=${port}`, '--user=root'], environment, sql);
        const dbEnvironment = { ...environment, DB_CONNECTION: 'mysql', DB_HOST: '127.0.0.1', DB_PORT: String(port),
            DB_DATABASE: database, DB_USERNAME: 'hoa_gap_test', DB_PASSWORD: password, DB_URL: '', HOA_ALLOW_DESTRUCTIVE_TEST_DATABASE: 'true' };
        report.database = { host: '127.0.0.1', port, name: database, privateTemporaryInstance: true };
        await tests('mariadb-tenant-and-certificate-races', settlementsOnly
            ? 'MySqlTenantPropertyPaymentsTest|MySqlPaymentConcurrencyTest|MySqlPropertyAndReceiptConcurrencyTest'
            : 'MySqlTenantPropertyPaymentsTest|MySqlCertificateDuesConcurrencyTest', dbEnvironment);
    }
    report.status = 'passed';
} catch (error) {
    report.status = 'failed';
    console.error(scrub(error.message));
    process.exitCode = 1;
} finally {
    if (server && server.exitCode === null) {
        const stopped = spawnSync(mysqladmin, ['--host=127.0.0.1', `--port=${port}`, '--user=root', '--connect-timeout=2', 'shutdown'], { windowsHide: true, timeout: 15_000, encoding: 'utf8' });
        if (stopped.status !== 0) server.kill();
    }
    await writeFile(path.join(directory, 'report.json'), JSON.stringify(report, null, 2));
    console.log(`Readiness report: ${path.join(directory, 'report.json')}. Synthetic artifacts retained; no production access or live mail/SMS.`);
}
