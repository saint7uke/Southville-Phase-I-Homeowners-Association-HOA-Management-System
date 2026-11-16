import { test, expect } from '@playwright/test';
import path from 'node:path';

test.beforeEach(async ({ page, baseURL }) => {
    test.skip(process.env.HOA_PROJECT_BROWSER !== 'true', 'Requires the isolated interface runner.');
    test.setTimeout(120_000);
    expect(new URL(baseURL).hostname).toBe('127.0.0.1');
    await page.route('**/*', route => new URL(route.request().url()).origin === new URL(baseURL).origin ? route.continue() : route.abort());
});

for (const [width, height, desktop] of [[1440, 900, true], [1024, 768, true], [721, 641, true], [390, 844, false], [1280, 600, false], [683, 384, false]]) {
    test(`registration scrolling ${width}x${height}`, async ({ page }) => {
        await page.setViewportSize({ width, height });
        await page.goto('/homeowner/register', { waitUntil: 'networkidle' });
        const panel = page.locator('#auth-card');
        const intro = page.locator('.hoa-portal-auth-intro');
        await page.locator('[data-role-card="Owner"]').click();
        await page.locator('#first_name').fill('Preserved');

        for (const locale of ['en', 'fil']) {
            await page.locator('[data-locale-switcher] select').selectOption(locale);
            await expect(page.locator('html')).toHaveAttribute('lang', locale);
            await expect(page.locator('[data-locale-switcher] select')).toBeEnabled();
            await expect(page.locator('#first_name')).toHaveValue('Preserved');
            expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1)).toBe(true);

            if (desktop) {
                await expect(panel).toHaveCSS('overflow-y', 'auto');
                expect(await page.evaluate(() => document.documentElement.scrollHeight <= innerHeight + 1)).toBe(true);
                const before = await intro.boundingBox();
                for (const child of await intro.locator('a, h1, .hoa-portal-auth-support').all()) {
                    const box = await child.boundingBox();
                    expect(box.y).toBeGreaterThanOrEqual(before.y - 1);
                    expect(box.y + box.height).toBeLessThanOrEqual(before.y + before.height + 1);
                }
                await panel.hover();
                await page.mouse.wheel(0, 800);
                await expect.poll(() => panel.evaluate(el => el.scrollTop)).toBeGreaterThan(0);
                expect(await intro.boundingBox()).toEqual(before);
                expect(await page.evaluate(() => window.scrollY)).toBe(0);
                await panel.evaluate(el => { el.scrollTop = el.scrollHeight; });
                await expect(page.locator('[name=privacy_consent]')).toBeInViewport();
                await expect(page.locator('button[type=submit]')).toBeInViewport();
                await page.locator('#first_name').evaluate(el => el.blur());
                await page.locator('#first_name').focus();
                await expect(page.locator('#first_name')).toBeInViewport();
                expect(await page.evaluate(() => window.scrollY)).toBe(0);
            } else {
                await expect(panel).toHaveCSS('overflow-y', 'visible');
                await expect(intro.locator('.hoa-portal-auth-support')).toBeVisible();
                const branding = await intro.boundingBox();
                const form = await panel.boundingBox();
                expect(form.y).toBeGreaterThanOrEqual(branding.y + branding.height - 1);
                await page.locator('button[type=submit]').scrollIntoViewIfNeeded();
                await expect(page.locator('button[type=submit]')).toBeInViewport();
                expect(await page.evaluate(() => window.scrollY)).toBeGreaterThan(0);
                expect(await panel.evaluate(el => el.scrollTop)).toBe(0);
            }
        }
        await page.locator('button[onclick="goBack()"]').click();
        await expect(page.locator('[data-role-card="Owner"]')).toBeInViewport();
        expect(await panel.evaluate(el => el.scrollTop)).toBe(0);
        await page.locator('[data-role-card="Owner"]').click();
        await expect(page.locator('#first_name')).toHaveValue('Preserved');
        await page.locator('#first_name').focus();
        await expect(page.locator('#first_name')).toBeInViewport();
        await page.screenshot({ path: path.join(process.env.HOA_PROJECT_REVIEW_DIR, `registration-scroll-${width}-${height}.png`), fullPage: true });
    });
}

test('registration layout does not apply to other authentication pages', async ({ page }) => {
    for (const url of ['/login', '/homeowner/forgot-password']) {
        await page.goto(url, { waitUntil: 'networkidle' });
        await expect(page.locator('.hoa-registration-page, .hoa-registration-shell')).toHaveCount(0);
        expect(await page.locator('body').evaluate(el => getComputedStyle(el).overflowY)).not.toBe('hidden');
    }
});
