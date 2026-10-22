// Export synthetic fixtures with HOA_BROWSER_EXPORT_NAVIGATION_FIXTURES=true and
// DashboardNavigationTest, then run with HOA_BROWSER_STATIC_NAVIGATION=true.
// These checks do not log into a live server or submit application requests.
import AxeBuilder from '@axe-core/playwright';
import { expect, test } from '@playwright/test';
import fs from 'node:fs/promises';
import path from 'node:path';

const publicRoot = path.resolve('public');
const fixtures = path.resolve('storage/framework/testing/dashboard-navigation');
const types = {
    '.js': 'text/javascript', '.css': 'text/css', '.png': 'image/png',
    '.svg': 'image/svg+xml', '.woff': 'font/woff', '.woff2': 'font/woff2',
};

test.beforeEach(async ({ page }) => {
    test.skip(process.env.HOA_BROWSER_STATIC_NAVIGATION !== 'true', 'Export the guarded DashboardNavigationTest fixtures first.');
    test.setTimeout(120_000);
    await page.route('**/*', async route => {
        const request = route.request();
        const url = new URL(request.url());
        if (request.method() !== 'GET') return route.abort();
        if (url.hostname === 'dashboard-navigation.test' && /^\/fixture\/(admin|staff|homeowner)$/.test(url.pathname)) {
            return route.fulfill({ contentType: 'text/html', body: await fs.readFile(path.join(fixtures, `${url.pathname.split('/').pop()}.html`)) });
        }
        if (/^\/livewire\/livewire(?:\.min)?\.js$/.test(url.pathname)) {
            return route.fulfill({ contentType: 'text/javascript', body: await fs.readFile('vendor/livewire/livewire/dist/livewire.js') });
        }
        const asset = path.resolve(publicRoot, `.${decodeURIComponent(url.pathname)}`);
        const type = types[path.extname(asset)];
        if (!asset.startsWith(publicRoot + path.sep) || !type) return route.abort();
        try { return route.fulfill({ contentType: type, body: await fs.readFile(asset) }); }
        catch { return route.abort(); }
    });
});

for (const [panel, count] of [['admin', 16], ['staff', 11], ['homeowner', 9]]) {
    test(`${panel} sidebar has distinct accessible icons in light/dark and responsive layouts`, async ({ page }, testInfo) => {
        await page.goto(`http://dashboard-navigation.test/fixture/${panel}`);
        await page.waitForFunction(() => Boolean(window.Alpine?.store('sidebar')));
        const sidebar = page.locator('#fi-main-sidebar');
        const links = sidebar.locator('.fi-sidebar-item > .fi-sidebar-item-btn');
        const icons = links.locator(':scope > svg.fi-sidebar-item-icon');
        await expect(links).toHaveCount(count);
        await expect(icons).toHaveCount(count);

        for (const width of [320, 768, 1440]) {
            await page.setViewportSize({ width, height: 900 });
            if (width < 1024) {
                await page.evaluate(() => window.Alpine.store('sidebar').close());
                await page.locator('.fi-topbar-open-sidebar-btn').click();
            }
            await expect(sidebar).toBeInViewport();

            for (const dark of [false, true]) {
                await page.emulateMedia({ colorScheme: dark ? 'dark' : 'light' });
                await page.evaluate(value => {
                    document.documentElement.classList.toggle('dark', value);
                    window.Alpine.store('theme', value ? 'dark' : 'light');
                }, dark);
                expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1)).toBe(true);

                const shapes = [];
                for (let index = 0; index < count; index++) {
                    const link = links.nth(index);
                    const icon = icons.nth(index);
                    await expect(link).toHaveAccessibleName(/\S/);
                    await expect(icon).toHaveAttribute('fill', 'none');
                    await expect(icon).toHaveAttribute('stroke', 'currentColor');
                    await expect(icon).toHaveAttribute('viewBox', '0 0 24 24');
                    const geometry = await icon.evaluate(node => {
                        const bounds = node.getBoundingClientRect();
                        const button = node.parentElement.getBoundingClientRect();
                        return { width: bounds.width, height: bounds.height, offset: Math.abs(bounds.y + bounds.height / 2 - button.y - button.height / 2) };
                    });
                    expect(geometry.width).toBe(24);
                    expect(geometry.height).toBe(24);
                    expect(geometry.offset).toBeLessThanOrEqual(1);
                    shapes.push(await icon.innerHTML());
                }
                expect(new Set(shapes).size).toBe(count);
                const active = sidebar.locator('.fi-sidebar-item.fi-active > a');
                await expect(active).toHaveAttribute('aria-current', 'page');

                if (width === 1440) {
                    const result = await new AxeBuilder({ page }).include('#fi-main-sidebar').withTags(['wcag2a', 'wcag2aa']).analyze();
                    expect(result.violations.filter(issue => ['serious', 'critical'].includes(issue.impact))).toEqual([]);
                }
                if (width !== 768) await page.screenshot({ path: testInfo.outputPath(`sidebar-${width}-${dark ? 'dark' : 'light'}.png`) });
            }
        }

        await links.first().focus();
        await expect(links.first()).toBeFocused();
        await page.keyboard.press('Tab');
        expect(await page.evaluate(() => Boolean(document.activeElement.closest('#fi-main-sidebar')))).toBe(true);
    });
}
