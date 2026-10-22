// Component/browser checks only. No application server, sign-in, persistence, or uploads.
import AxeBuilder from '@axe-core/playwright';
import { expect, test } from '@playwright/test';
import fs from 'node:fs/promises';
import path from 'node:path';

const root = process.cwd();
const publicRoot = path.resolve(root, 'public');
const fixtures = path.resolve(root, 'storage/framework/testing/global-locale-static');
const types = { '.js': 'text/javascript', '.css': 'text/css', '.png': 'image/png', '.webp': 'image/webp', '.svg': 'image/svg+xml', '.woff2': 'font/woff2', '.woff': 'font/woff' };

test.beforeEach(async ({ page }) => {
    test.skip(process.env.HOA_BROWSER_STATIC_LOCALE_FIXTURES !== 'true', 'Export synthetic rendered fixtures with LanguageSwitcherTest first.');
    test.setTimeout(90_000);
    await page.route('**/*', async route => {
        const request = route.request();
        const url = new URL(request.url());
        if (request.method() === 'POST' && url.pathname === '/locale') {
            return route.fulfill({ contentType: 'application/json', body: JSON.stringify({ locale: request.postDataJSON().locale }) });
        }
        if (request.method() !== 'GET') return route.abort();
        if (/^\/fixture\/[a-z-]+$/.test(url.pathname)) {
            return route.fulfill({ contentType: 'text/html', body: await fs.readFile(path.join(fixtures, `${url.pathname.split('/').pop()}.html`)) });
        }
        if (url.pathname === '/livewire/livewire.js') {
            return route.fulfill({ contentType: 'text/javascript', body: await fs.readFile(path.resolve(root, 'vendor/livewire/livewire/dist/livewire.js')) });
        }
        const asset = path.resolve(publicRoot, `.${decodeURIComponent(url.pathname)}`);
        const type = types[path.extname(asset)];
        if (!asset.startsWith(publicRoot + path.sep) || !type) return route.abort();
        try { return await route.fulfill({ contentType: type, body: await fs.readFile(asset) }); }
        catch { return route.abort(); }
    });
});

async function open(page, name) {
    await page.goto(`http://locale-switcher.test/fixture/${name}`);
    await expect(page.locator('#hoa-locale')).toHaveCount(1);
    await expect(page.locator('#hoa-locale')).toBeVisible();
    await expect(page.locator('html')).toHaveAttribute('data-locale-ready', 'true');
}

async function compact(page) {
    const select = page.getByRole('combobox', { name: /^(Language|Wika)$/ });
    expect(await select.evaluate(node => getComputedStyle(node).fontSize)).toBe('14px');
    expect(await select.evaluate(node => getComputedStyle(node).minBlockSize)).toBe('44px');
    // Firefox can report 43.999992px for a computed 44px target.
    expect(Math.round((await select.boundingBox()).height * 100) / 100).toBeGreaterThanOrEqual(44);
    expect(await page.evaluate(() => document.documentElement.scrollWidth > innerWidth + 1)).toBe(false);
}

test('rendered authentication controls fit desktop tablet mobile and light/dark surroundings', async ({ page }, testInfo) => {
    for (const screen of ['login', 'registration', 'forgot-password', 'reset-password', 'filament-password', 'submitted', 'expired', 'legacy-portal']) {
        await open(page, screen);
        for (const width of [320, 768, 1440]) {
            await page.setViewportSize({ width, height: 900 });
            await compact(page);
        }
        await page.emulateMedia({ colorScheme: 'dark' });
        await compact(page);
        const axe = await new AxeBuilder({ page }).include('[data-locale-switcher]').withTags(['wcag2a', 'wcag2aa']).analyze();
        expect(axe.violations.filter(issue => ['serious', 'critical'].includes(issue.impact))).toEqual([]);
        await page.emulateMedia({ colorScheme: 'light' });
    }
    await page.screenshot({ path: testInfo.outputPath('legacy-portal.png') });
});

test('React navbar keeps one visible control outside the mobile navigation and preserves menu state', async ({ page }, testInfo) => {
    await open(page, 'homepage');
    for (const width of [320, 768, 1440]) {
        await page.setViewportSize({ width, height: 900 });
        await compact(page);
    }
    await page.setViewportSize({ width: 320, height: 900 });
    await expect(page.locator('.hoa-nav-utilities #hoa-locale')).toBeVisible();
    await page.getByRole('button', { name: 'Open navigation menu' }).click();
    await page.locator('#hoa-locale').selectOption('fil');
    await expect(page.locator('html')).toHaveAttribute('lang', 'fil');
    await expect(page.getByRole('button', { name: 'Close navigation menu' })).toHaveAttribute('aria-expanded', 'true');
    await page.getByRole('button', { name: 'Close navigation menu' }).press('Escape');
    await page.evaluate(() => scrollTo(0, 600));
    await expect(page.locator('[data-nav]')).toHaveClass(/is-scrolled/);
    await compact(page);
    const axe = await new AxeBuilder({ page }).include('[data-nav]').withTags(['wcag2a', 'wcag2aa']).analyze();
    expect(axe.violations.filter(issue => ['serious', 'critical'].includes(issue.impact))).toEqual([]);
    await page.screenshot({ path: testInfo.outputPath('public-mobile.png') });
});

test('rendered dashboard controls align before the avatar and have theme-aware contrast', async ({ page }, testInfo) => {
    for (const panel of ['admin', 'staff', 'homeowner']) {
        await open(page, panel);
        for (const width of [320, 768, 1440]) {
            await page.setViewportSize({ width, height: 900 });
            await compact(page);
        }
        const select = await page.locator('#hoa-locale').boundingBox();
        const avatar = await page.locator('.fi-user-menu-trigger').first().boundingBox();
        expect(select.x + select.width).toBeLessThanOrEqual(avatar.x);
        expect(Math.abs(select.y + select.height / 2 - avatar.y - avatar.height / 2)).toBeLessThanOrEqual(4);
        for (const theme of ['dark', 'light']) {
            // Static snapshots have no active Filament session/controller. Check the actual
            // theme CSS directly; the separate opt-in live suite exercises the real event.
            await page.evaluate(value => document.documentElement.classList.toggle('dark', value === 'dark'), theme);
            if (theme === 'dark') await expect(page.locator('html')).toHaveClass(/dark/);
            else await expect(page.locator('html')).not.toHaveClass(/dark/);
            const axe = await new AxeBuilder({ page }).include('[data-locale-switcher]').withTags(['wcag2a', 'wcag2aa']).analyze();
            expect(axe.violations.filter(issue => ['serious', 'critical'].includes(issue.impact))).toEqual([]);
            await page.screenshot({ path: testInfo.outputPath(`${panel}-${theme}.png`) });
        }
    }
});

test('pending/failure feedback is stable, requests are deduplicated and unsaved browser fields survive', async ({ page }) => {
    await open(page, 'login');
    await page.locator('#email').fill('unsaved@example.test');
    await page.locator('#password').fill('UnsavedPassword!2026');
    let release;
    let called;
    let count = 0;
    const observed = new Promise(resolve => { called = resolve; });
    const response = new Promise(resolve => { release = resolve; });
    await page.route('**/locale', async route => {
        count++;
        called();
        await response;
        await route.fulfill({ status: 503, contentType: 'application/json', body: '{}' });
    });
    const original = await page.locator('#hoa-locale').boundingBox();
    await page.locator('#hoa-locale').focus();
    await expect(page.locator('#hoa-locale')).toBeFocused();
    await page.locator('#hoa-locale').selectOption('fil');
    await observed;
    await expect(page.locator('#hoa-locale')).toBeDisabled();
    await expect(page.locator('.hoa-locale-spinner')).toBeVisible();
    await page.evaluate(() => document.body.appendChild(document.createElement('span')));
    await expect(page.locator('#hoa-locale')).toHaveValue('fil');
    await page.locator('#hoa-locale').dispatchEvent('change');
    const pending = await page.locator('#hoa-locale').boundingBox();
    expect([pending.width, pending.height]).toEqual([original.width, original.height]);
    release();
    await expect(page.locator('#hoa-locale')).toBeEnabled();
    await expect(page.locator('#hoa-locale')).toHaveValue('en');
    await expect(page.locator('#hoa-locale-status')).toContainText('Language could not be changed');
    expect(count).toBe(1);
    await page.unroute('**/locale');
    count = 0;
    page.on('request', request => { if (new URL(request.url()).pathname === '/locale') count++; });
    await page.evaluate(() => { for (let i = 0; i < 3; i++) document.dispatchEvent(new Event('livewire:navigated')); });
    await page.locator('#hoa-locale').selectOption('fil');
    await expect(page.locator('html')).toHaveAttribute('lang', 'fil');
    await expect(page.locator('#hoa-locale')).toBeEnabled();
    await expect(page.locator('#email')).toHaveValue('unsaved@example.test');
    await expect(page.locator('#password')).toHaveValue('UnsavedPassword!2026');
    expect(count).toBe(1);
    await page.locator('#hoa-locale').selectOption('en');
    await expect(page.locator('html')).toHaveAttribute('lang', 'en');
    expect(count).toBe(2);
});
