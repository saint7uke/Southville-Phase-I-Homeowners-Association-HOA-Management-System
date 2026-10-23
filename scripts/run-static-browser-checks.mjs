import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { spawnSync } from 'node:child_process';
import { checkFixtureAssets, fixtureFiles } from './static-browser-assets.mjs';

const root = path.resolve(fileURLToPath(new URL('..', import.meta.url)));
const args = process.argv.slice(2);
const prepareOnly = args.includes('--prepare-only');
const preflightOnly = args.includes('--preflight-only');
const browserArgs = args.filter(arg => !['--prepare-only', '--preflight-only'].includes(arg));
const defaultProjects = browserArgs.some(arg => arg === '--project' || arg.startsWith('--project='))
    ? [] : ['--project=chromium', '--project=firefox', '--project=webkit'];
const environment = {
    ...process.env,
    APP_ENV: 'testing', APP_CONFIG_CACHE: 'bootstrap/cache/config-static-browser-testing.php',
    DB_CONNECTION: 'sqlite', DB_DATABASE: ':memory:', DB_URL: '',
    CACHE_STORE: 'array', SESSION_DRIVER: 'array', QUEUE_CONNECTION: 'sync', MAIL_MAILER: 'array',
    HOA_BROWSER_EXPORT_NAVIGATION_FIXTURES: 'true', HOA_BROWSER_EXPORT_REQUEST_GUIDANCE_FIXTURES: 'true',
    HOA_BROWSER_STATIC_NAVIGATION: 'true', HOA_BROWSER_STATIC_REQUEST_GUIDANCE: 'true',
    // Prevent Playwright from attaching to or starting a real application server.
    PLAYWRIGHT_BASE_URL: 'http://static-fixtures.invalid',
    PLAYWRIGHT_HTML_OUTPUT_DIR: 'playwright-report/static-fixtures',
};

function run(binary, commandArgs) {
    console.log(`Running ${path.basename(binary)} ${commandArgs.join(' ')}`);
    const result = spawnSync(binary, commandArgs, { cwd: root, env: environment, stdio: 'inherit', shell: false });
    if (result.error) throw result.error;
    if (result.status !== 0) process.exit(result.status ?? 1);
}

if (!preflightOnly) {
    run(process.execPath, ['node_modules/vite/bin/vite.js', 'build']);
    const php = process.env.PLAYWRIGHT_PHP_BINARY ?? 'php';
    run(php, ['vendor/bin/phpunit', 'tests/Feature/DashboardNavigationTest.php', '--filter=test_sidebars_render_without_changing_panel_access', '--colors=never']);
    run(php, ['vendor/bin/phpunit', 'tests/Feature/CohabitationRequestGuidanceTest.php', '--filter=test_guidance_is_accessible_and_escapes_record_text_in_both_languages', '--colors=never']);
}
const errors = await checkFixtureAssets(root);
if (errors.length) {
    console.error(errors.join('\n'));
    process.exit(1);
}
console.log(`Asset preflight passed for ${fixtureFiles.length} synthetic fixtures.`);
if (!prepareOnly && !preflightOnly) {
    run(process.execPath, ['node_modules/@playwright/test/cli.js', 'test',
        'tests/Browser/dashboard-navigation.spec.js', 'tests/Browser/cohabitation-request-guidance.spec.js',
        '--workers=1', '--retries=0', '--output=storage/framework/testing/static-browser-results',
        ...defaultProjects, ...browserArgs,
    ]);
}
