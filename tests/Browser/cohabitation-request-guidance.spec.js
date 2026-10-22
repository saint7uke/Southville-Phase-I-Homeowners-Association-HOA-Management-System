// Rendered-page checks only: synthetic in-memory SQLite fixtures, no live server/accounts.
import AxeBuilder from '@axe-core/playwright';
import { expect, test } from '@playwright/test';
import fs from 'node:fs/promises';
import path from 'node:path';

const publicRoot = path.resolve('public');
const fixtures = path.resolve('storage/framework/testing/cohabitation-request-guidance');
const types = { '.js': 'text/javascript', '.css': 'text/css', '.png': 'image/png', '.svg': 'image/svg+xml', '.woff2': 'font/woff2' };

test.beforeEach(async ({ page }) => {
    test.skip(process.env.HOA_BROWSER_STATIC_REQUEST_GUIDANCE !== 'true', 'Export the guarded CohabitationRequestGuidanceTest fixtures first.');
    test.setTimeout(120_000);
    await page.route('**/*', async route => {
        const request = route.request();
        const url = new URL(request.url());
        if (url.hostname !== 'certificate-guidance.test') return route.abort();
        if (request.method() === 'POST' && url.pathname === '/locale') {
            return route.fulfill({ contentType: 'application/json', body: JSON.stringify({ locale: request.postDataJSON().locale }) });
        }
        if (request.method() !== 'GET') return route.abort();
        if (/^\/fixture\/(en|fil)-(pending|approved|rejected|completed)$/.test(url.pathname)) {
            return route.fulfill({ contentType: 'text/html', body: await fs.readFile(path.join(fixtures, `${url.pathname.split('/').pop()}.html`)) });
        }
        const asset = path.resolve(publicRoot, `.${decodeURIComponent(url.pathname)}`);
        const type = types[path.extname(asset)];
        if (!asset.startsWith(publicRoot + path.sep) || !type) return route.abort();
        try { return route.fulfill({ contentType: type, body: await fs.readFile(asset) }); }
        catch { return route.abort(); }
    });
});

test('Cohabitation guidance has readable status-specific copy and responsive keyboard navigation', async ({ page }, testInfo) => {
    for (const locale of ['en', 'fil']) {
        for (const status of ['pending', 'approved', 'rejected', 'completed']) {
            await page.goto(`http://certificate-guidance.test/fixture/${locale}-${status}`);
            await expect(page.locator('html')).toHaveAttribute('lang', locale);
            await expect(page.getByRole('heading', { level: 1 })).toHaveText(locale === 'fil' ? 'Paghahanda ng sertipiko ng Cohabitation' : 'Cohabitation certificate preparation');
            await expect(page.locator('main')).not.toContainText('Stack Trace');
            if (status === 'rejected' || status === 'completed') {
                await expect(page.locator('main')).not.toContainText('These details are completed in Certificates after approval.');
            }
            for (const width of [320, 768, 1440]) {
                await page.setViewportSize({ width, height: 900 });
                expect(await page.evaluate(() => document.documentElement.scrollWidth > innerWidth + 1)).toBe(false);
                const back = page.getByRole('link', { name: locale === 'fil' ? 'Bumalik sa mga kahilingan' : 'Back to requests', exact: true });
                await expect(back).toBeVisible();
                await expect(back).toHaveAttribute('href', /\/homeowner\/service-requests\/\d+$/);
            }
        }
    }
    await page.goto('http://certificate-guidance.test/fixture/en-pending');
    await page.setViewportSize({ width: 320, height: 900 });
    await expect(page.locator('html')).toHaveAttribute('data-locale-ready', 'true');
    await page.keyboard.press('Tab');
    await expect(page.locator('#hoa-locale')).toBeFocused();
    await page.keyboard.press('Tab');
    await expect(page.getByRole('link', { name: 'Back to requests', exact: true })).toBeFocused();
    expect(await page.getByRole('link', { name: 'Back to requests' }).evaluate(node => getComputedStyle(node).outlineStyle)).not.toBe('none');
    await page.screenshot({ path: testInfo.outputPath('pending-mobile-keyboard.png') });
});

test('guidance switches only marked copy and passes representative light/dark Axe checks', async ({ page }, testInfo) => {
    await page.goto('http://certificate-guidance.test/fixture/en-approved');
    await expect(page.locator('html')).toHaveAttribute('data-locale-ready', 'true');
    const ticket = await page.locator('dd').first().textContent();
    let posts = 0;
    page.on('request', request => { if (request.method() === 'POST' && new URL(request.url()).pathname === '/locale') posts++; });
    await page.locator('#hoa-locale').selectOption('fil');
    await expect(page.getByRole('heading', { level: 1 })).toHaveText('Paghahanda ng sertipiko ng Cohabitation');
    await expect(page.locator('main')).toContainText('Aprubado na ang iyong kahilingan para sa Cohabitation.');
    await expect(page.locator('dd').first()).toHaveText(ticket);
    expect(posts).toBe(1);
    const backgrounds = [];
    for (const dark of [false, true]) {
        await page.emulateMedia({ colorScheme: dark ? 'dark' : 'light' });
        await page.evaluate(value => document.documentElement.classList.toggle('dark', value), dark);
        expect(await page.evaluate(() => matchMedia('(prefers-color-scheme: dark)').matches)).toBe(dark);
        backgrounds.push(await page.locator('main section').evaluate(node => getComputedStyle(node).backgroundColor));
        const result = await new AxeBuilder({ page }).withTags(['wcag2a', 'wcag2aa']).analyze();
        expect(result.violations.filter(issue => ['serious', 'critical'].includes(issue.impact))).toEqual([]);
        await page.screenshot({ path: testInfo.outputPath(`guidance-${dark ? 'dark' : 'light'}.png`) });
    }
    expect(backgrounds[0]).not.toBe(backgrounds[1]);
});
