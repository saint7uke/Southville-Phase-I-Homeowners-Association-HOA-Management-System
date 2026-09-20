import { defineConfig, devices } from '@playwright/test';

const phpBinary = process.env.PLAYWRIGHT_PHP_BINARY
    ?? (process.platform === 'win32' ? '"F:\\Xampp 8\\php\\php.exe"' : 'php');

export default defineConfig({
    testDir: './tests/Browser',
    timeout: 30_000,
    // The bundled PHP development server is single-process on Windows and is
    // also the CI webServer. Serialize CI requests to avoid dropped Livewire
    // login submissions when multiple browser projects start concurrently.
    workers: process.env.CI ? 1 : undefined,
    expect: { timeout: 5_000 },
    reporter: [['list'], ['html', { open: 'never' }]],
    use: {
        baseURL: process.env.PLAYWRIGHT_BASE_URL ?? 'http://127.0.0.1:8000',
        trace: 'retain-on-failure',
        screenshot: 'only-on-failure',
    },
    webServer: process.env.PLAYWRIGHT_BASE_URL ? undefined : {
        command: `${phpBinary} -S 127.0.0.1:8000 ../vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php`,
        cwd: './public',
        url: 'http://127.0.0.1:8000/up',
        reuseExistingServer: true,
        timeout: 120_000,
    },
    projects: [
        { name: 'chromium', use: { ...devices['Desktop Chrome'] } },
        { name: 'edge', use: { ...devices['Desktop Edge'], channel: 'msedge' } },
        { name: 'firefox', use: { ...devices['Desktop Firefox'] } },
        { name: 'webkit', use: { ...devices['Desktop Safari'] } },
    ],
});
