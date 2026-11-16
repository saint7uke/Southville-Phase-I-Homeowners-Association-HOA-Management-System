import { mkdtemp, mkdir } from 'node:fs/promises';
import { randomBytes } from 'node:crypto';
import os from 'node:os';
import path from 'node:path';
import { spawn, spawnSync } from 'node:child_process';
import { createServer } from 'node:net';

const directory = await mkdtemp(path.join(os.tmpdir(), 'hoa-alert-browser-interface-'));
const paymentFixtures = process.argv.includes('--installments') || process.argv.includes('--dues-availability');
const storage = path.join(directory, 'storage');
for (const child of ['framework/views', 'framework/cache/data', 'framework/sessions', 'logs', 'app/private', 'app/public']) {
    await mkdir(path.join(storage, child), { recursive: true });
}
const environment = { ...process.env, APP_ENV: 'testing', APP_DEBUG: 'false',
    APP_KEY: `base64:${randomBytes(32).toString('base64')}`, LARAVEL_STORAGE_PATH: storage,
    VIEW_COMPILED_PATH: path.join(storage, 'framework/views'),
    APP_CONFIG_CACHE: path.join(directory, 'unused-config.php'), DB_CONNECTION: 'sqlite', DB_DATABASE: path.join(directory, 'testing.sqlite'), DB_URL: '',
    CACHE_STORE: 'array', SESSION_DRIVER: 'file', MAIL_MAILER: 'array', QUEUE_CONNECTION: 'sync', BCRYPT_ROUNDS: '4',
    HOA_PROJECT_BROWSER: 'true', HOA_PAYMENT_BROWSER: paymentFixtures ? 'true' : 'false', HOA_PROJECT_REVIEW_DIR: directory, SESSION_SECURE_COOKIE: 'false', SESSION_COOKIE: 'hoa_project_test', CI: '1',
    TEXTBEE_ENABLED: 'false', TEXTBEE_TEST_MODE: 'true', TEXTBEE_TEST_NUMBERS: '',
};
function run(args) {
    const result = spawnSync('php', args, { env: environment, stdio: 'inherit', windowsHide: true });
    if (result.error || result.status !== 0) throw new Error('Isolated fixture preparation failed.');
}
console.log(`Synthetic interface browser database: ${environment.DB_DATABASE}`);
run(['artisan', 'migrate', '--force', '--no-interaction']);
run(['artisan', 'db:seed', `--class=Tests\\Support\\${paymentFixtures ? 'InstallmentBrowserSeeder' : 'InterfaceBrowserSeeder'}`, '--force', '--no-interaction']);
// Keep cold compilation outside browser interaction timeouts; this cache is private
// to the disposable database/storage and never writes application compiled views.
if (paymentFixtures) run(['artisan', 'view:cache', '--no-interaction']);
const socket = createServer();
await new Promise(resolve => socket.listen(0, '127.0.0.1', resolve));
const port = socket.address().port;
await new Promise(resolve => socket.close(resolve));
environment.APP_URL = `http://127.0.0.1:${port}`;
environment.PLAYWRIGHT_BASE_URL = environment.APP_URL;
// Browser uploads need the real HTTP upload lifecycle, not Livewire's
// request-local tmp-for-tests fake disk. The database and all storage stay isolated.
const serverEnvironment = { ...environment, APP_ENV: 'local' };
// Cold Filament view compilation on Windows can exceed the default 60 seconds.
// This override applies only to the disposable installment browser server.
const serverOptions = paymentFixtures ? ['-d', 'max_execution_time=180'] : [];
const server = spawn('php', [...serverOptions, '-d', `error_log=${path.join(directory, 'php-errors.log')}`, '-S', `127.0.0.1:${port}`, '../tests/Support/isolated-interface-browser-router.php'],
    { cwd: path.resolve('public'), env: serverEnvironment, stdio: 'ignore', windowsHide: true });
try {
    let ready = false;
    for (let attempt = 0; attempt < 80; attempt++) {
        try { if ((await fetch(`${environment.APP_URL}/up`)).ok) { ready = true; break; } } catch {}
        await new Promise(resolve => setTimeout(resolve, 250));
    }
    if (!ready) throw new Error('Isolated project server failed to start.');
    const scrollOnly = process.argv.includes('--registration-scroll');
    const picturesOnly = process.argv.includes('--concern-pictures');
    const documentOnly = process.argv.includes('--registration-document');
    const spec = process.argv.includes('--dues-availability') ? 'tests/Browser/dues-availability.spec.js' : process.argv.includes('--installments') ? 'tests/Browser/installments.spec.js' : documentOnly ? 'tests/Browser/registration-document.spec.js' : picturesOnly ? 'tests/Browser/concern-pictures.spec.js' : scrollOnly ? 'tests/Browser/registration-scroll.spec.js' : 'tests/Browser/interface-revisions.spec.js';
    const result = spawnSync(process.execPath, ['node_modules/@playwright/test/cli.js', 'test', spec, '--project=chromium', '--workers=1', '--retries=0', '--output', path.join(directory, 'results'), ...process.argv.slice(2).filter(argument => !['--registration-scroll', '--concern-pictures', '--registration-document', '--installments', '--dues-availability'].includes(argument))],
        { env: environment, stdio: 'inherit', windowsHide: true });
    if (result.error) throw result.error;
    process.exitCode = result.status ?? 1;
} finally { server.kill(); }
