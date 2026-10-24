// Own server, new synthetic SQLite database, fake mail; never reuse the user's app/database.
import { spawn, spawnSync } from 'node:child_process';
import { once } from 'node:events';
import fs from 'node:fs';
import net from 'node:net';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { assertNoTestBuildOrigin } from './public-ui-build-assets.mjs';

const root = path.resolve(fileURLToPath(new URL('..', import.meta.url)));
const directory = path.join(root, 'storage/framework/testing', `my-household-browser-test-${Date.now()}`);
fs.mkdirSync(directory, { recursive: true });
const database = path.join(directory, 'database.sqlite');
fs.writeFileSync(database, '', { flag: 'wx' });
const probe = net.createServer();
probe.listen(0, '127.0.0.1');
await once(probe, 'listening');
const port = probe.address().port;
await new Promise(resolve => probe.close(resolve));
const baseURL = `http://127.0.0.1:${port}`;
const environment = {
    ...process.env,
    APP_ENV: 'testing', APP_URL: baseURL, ASSET_URL: '', APP_DEBUG: 'false',
    APP_KEY: 'base64:' + Buffer.from('0123456789abcdef0123456789abcdef').toString('base64'),
    APP_CONFIG_CACHE: path.join(directory, 'config.php'), APP_ROUTES_CACHE: path.join(directory, 'routes.php'),
    DB_CONNECTION: 'sqlite', DB_DATABASE: database, DB_URL: '',
    SESSION_DRIVER: 'database', SESSION_SECURE_COOKIE: 'false', SESSION_DOMAIN: '', SESSION_COOKIE: 'my_household_test_session',
    CACHE_STORE: 'array', QUEUE_CONNECTION: 'sync', MAIL_MAILER: 'array', BROADCAST_CONNECTION: 'null',
    HOA_BROWSER_MY_HOUSEHOLD: 'true',
    PLAYWRIGHT_BASE_URL: baseURL, PLAYWRIGHT_HTML_OUTPUT_DIR: path.join(directory, 'html-report'),
};
const php = process.env.PLAYWRIGHT_PHP_BINARY ?? 'php';
function run(binary, args) {
    const result = spawnSync(binary, args, { cwd: root, env: environment, stdio: 'inherit', windowsHide: true });
    if (result.error) throw result.error;
    if (result.status !== 0) throw new Error(`${path.basename(binary)} exited with ${result.status}`);
}

run(process.execPath, ['node_modules/vite/bin/vite.js', 'build']);
await assertNoTestBuildOrigin(root, baseURL);
run(php, ['artisan', 'migrate', '--force', '--no-interaction']);
run(php, ['artisan', 'db:seed', '--class=Tests\\Support\\MyHouseholdBrowserSeeder', '--force', '--no-interaction']);
console.log(`Synthetic database: ${database}\nOwned server: ${baseURL}\nArtifacts: ${directory}`);
const log = fs.openSync(path.join(directory, 'server.log'), 'wx');
const server = spawn(php, ['-S', `127.0.0.1:${port}`, '-t', path.join(root, 'public'), path.join(root, 'vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php')], {
    cwd: path.join(root, 'public'), env: environment, stdio: ['ignore', log, log], windowsHide: true,
});
server.on('error', error => { console.error(error.message); });
process.once('SIGINT', () => { server.kill(); process.exitCode = 130; });
try {
    let healthy = false;
    for (let attempt = 0; attempt < 60; attempt++) {
        if (server.exitCode !== null) throw new Error('The isolated server exited before readiness.');
        try { healthy = (await fetch(baseURL + '/up', { signal: AbortSignal.timeout(2000) })).ok; } catch { /* owned server starting */ }
        if (healthy) break;
        await new Promise(resolve => setTimeout(resolve, 250));
    }
    if (!healthy) throw new Error('The isolated PHP server did not become ready.');
    const args = process.argv.slice(2);
    const projects = args.some(arg => arg === '--project' || arg.startsWith('--project=')) ? [] : ['--project=chromium', '--project=firefox', '--project=webkit'];
    run(process.execPath, ['node_modules/@playwright/test/cli.js', 'test', 'tests/Browser/my-household.spec.js', '--workers=1', '--retries=0', `--output=${path.join(directory, 'results')}`, ...projects, ...args]);
} finally {
    if (server.exitCode === null) {
        const stopped = once(server, 'exit');
        server.kill();
        await stopped;
    }
    fs.closeSync(log);
    console.log('Owned test server stopped; synthetic database and evidence retained.');
}
