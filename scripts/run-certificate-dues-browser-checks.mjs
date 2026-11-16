import { mkdtemp } from 'node:fs/promises';
import os from 'node:os';
import path from 'node:path';
import { spawn, spawnSync } from 'node:child_process';
import { createServer } from 'node:net';

const directory = await mkdtemp(path.join(os.tmpdir(), 'hoa-alert-browser-certificate-dues-'));
const environment = { ...process.env, APP_ENV: 'testing', APP_DEBUG: 'false',
    APP_CONFIG_CACHE: path.join(directory, 'config.php'), DB_CONNECTION: 'sqlite', DB_DATABASE: path.join(directory, 'testing.sqlite'), DB_URL: '',
    CACHE_STORE: 'array', SESSION_DRIVER: 'file', MAIL_MAILER: 'array', QUEUE_CONNECTION: 'sync', BCRYPT_ROUNDS: '4',
    HOA_CERTIFICATE_DUES_BROWSER: 'true', SESSION_SECURE_COOKIE: 'false', SESSION_COOKIE: 'hoa_certificate_dues_session', CI: '1',
    TEXTBEE_ENABLED: 'false', TEXTBEE_TEST_MODE: 'true', TEXTBEE_TEST_NUMBERS: '',
};
function run(args) {
    const result = spawnSync('php', args, { env: environment, stdio: 'inherit', shell: false, windowsHide: true });
    if (result.error) throw result.error;
    if (result.status !== 0) process.exit(result.status ?? 1);
}
console.log(`Disposable certificate dues browser database: ${environment.DB_DATABASE}`);
run(['artisan', 'migrate', '--force', '--no-interaction']);
run(['artisan', 'db:seed', '--class=Tests\\Support\\CertificateDuesBrowserSeeder', '--force', '--no-interaction']);
const socket = createServer();
await new Promise(resolve => socket.listen(0, '127.0.0.1', resolve));
const port = socket.address().port;
await new Promise(resolve => socket.close(resolve));
environment.APP_URL = `http://127.0.0.1:${port}`;
environment.PLAYWRIGHT_BASE_URL = environment.APP_URL;
const server = spawn('php', ['-d', `error_log=${path.join(directory, 'php-errors.log')}`, '-S', `127.0.0.1:${port}`, '../vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php'],
    { cwd: path.resolve('public'), env: environment, stdio: 'ignore', windowsHide: true });
try {
    let ready = false;
    for (let attempt = 0; attempt < 60; attempt++) {
        try { if ((await fetch(`${environment.APP_URL}/up`)).ok) { ready = true; break; } } catch {}
        await new Promise(resolve => setTimeout(resolve, 250));
    }
    if (!ready) throw new Error(`Isolated server failed; inspect ${directory}.`);
    const result = spawnSync(process.execPath, ['node_modules/@playwright/test/cli.js', 'test', 'tests/Browser/certificate-dues.spec.js', '--project=chromium', '--workers=1', '--retries=0', ...process.argv.slice(2)],
        { env: environment, stdio: 'inherit', shell: false, windowsHide: true });
    if (result.error) throw result.error;
    process.exitCode = result.status ?? 1;
} finally { server.kill(); }
