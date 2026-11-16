import { mkdtemp } from 'node:fs/promises';
import os from 'node:os';
import path from 'node:path';
import { spawn, spawnSync } from 'node:child_process';
import { createServer } from 'node:net';

const directory = await mkdtemp(path.join(os.tmpdir(), 'hoa-alert-browser-sms-'));
const environment = { ...process.env, APP_ENV: 'testing', APP_DEBUG: 'false',
    APP_CONFIG_CACHE: path.join(directory, 'unused-config.php'),
    DB_CONNECTION: 'sqlite', DB_DATABASE: path.join(directory, 'testing.sqlite'), DB_URL: '',
    CACHE_STORE: 'database', SESSION_DRIVER: 'file', MAIL_MAILER: 'array', QUEUE_CONNECTION: 'sync', BCRYPT_ROUNDS: '4',
    HOA_SMS_BROWSER_FIXTURES: 'true', SESSION_SECURE_COOKIE: 'false', SESSION_COOKIE: 'hoa_sms_browser_session', CI: '1',
    TEXTBEE_ENABLED: 'false', TEXTBEE_TEST_MODE: 'true', TEXTBEE_TEST_NUMBERS: '',
};
function run(binary, args) {
    const result = spawnSync(binary, args, { env: environment, stdio: 'inherit', shell: false, windowsHide: true });
    if (result.error) throw result.error;
    if (result.status !== 0) process.exit(result.status ?? 1);
}
console.log(`Disposable SMS browser database: ${environment.DB_DATABASE}`);
run('php', ['artisan', 'migrate', '--force', '--no-interaction']);
run('php', ['artisan', 'db:seed', '--class=Tests\\Support\\SmsBrowserSeeder', '--force', '--no-interaction']);
const socket = createServer();
await new Promise(resolve => socket.listen(0, '127.0.0.1', resolve));
const port = socket.address().port;
await new Promise(resolve => socket.close(resolve));
environment.APP_URL = `http://127.0.0.1:${port}`;
environment.PLAYWRIGHT_BASE_URL = environment.APP_URL;
const server = spawn('php', ['-d', `error_log=${path.join(directory, 'php-errors.log')}`, '-S', `127.0.0.1:${port}`, '../tests/Support/sms-browser-server.php'],
    { cwd: path.resolve('public'), env: environment, stdio: 'ignore', windowsHide: true });
try {
    let ready = false;
    for (let attempt = 0; attempt < 80; attempt++) {
        try { if ((await fetch(`${environment.APP_URL}/up`)).ok) { ready = true; break; } } catch {}
        await new Promise(resolve => setTimeout(resolve, 250));
    }
    if (!ready) throw new Error(`The isolated SMS browser server did not start. Check ${directory}.`);
    const result = spawnSync(process.execPath, ['node_modules/@playwright/test/cli.js', 'test', 'tests/Browser/sms.spec.js', '--project=chromium', '--workers=1', '--retries=0', ...process.argv.slice(2)],
        { env: environment, stdio: 'inherit', shell: false, windowsHide: true });
    if (result.error) throw result.error;
    process.exitCode = result.status ?? 1;
} finally { server.kill(); }
