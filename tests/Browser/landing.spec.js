import AxeBuilder from '@axe-core/playwright';
import { expect, test } from '@playwright/test';

test('landing page has no serious automated accessibility violations', async ({ page }) => {
    await page.goto('/', { waitUntil: 'networkidle' });
    await expect(page.getByRole('heading', { level: 1 })).toBeVisible();
    const brandLogo = page.locator('.hoa-navbar .hoa-brand-mark img');
    await expect(brandLogo).toBeVisible();
    await expect(brandLogo).toHaveAttribute('src', /images\/HOA\.png$/);
    await expect.poll(() => brandLogo.evaluate((image) => image.naturalWidth)).toBeGreaterThan(0);
    await expect(page.locator('link[rel="icon"]')).toHaveAttribute('href', /images\/HOA\.png$/);
    await expect(page.locator('#how-it-works')).toContainText('Register');
    await expect(page.locator('#how-it-works')).toContainText('Verify');
    await expect(page.locator('#how-it-works')).toContainText('Access your panel');
    await expect(page.locator('#how-it-works')).toContainText('Manage everything');
    await expect.poll(() => page.locator('[data-hero-reveal]').last().evaluate((element) => getComputedStyle(element).opacity)).toBe('1');

    const results = await new AxeBuilder({ page }).withTags(['wcag2a', 'wcag2aa', 'wcag21aa', 'wcag22aa']).analyze();
    expect(results.violations.filter((violation) => ['serious', 'critical'].includes(violation.impact))).toEqual([]);
});

for (const viewport of [
    { width: 320, height: 700 },
    { width: 768, height: 1024 },
    { width: 1440, height: 900 },
    { width: 1920, height: 1080 },
]) {
    test(`landing page reflows without horizontal overflow at ${viewport.width}px`, async ({ page }) => {
        await page.setViewportSize(viewport);
        await page.goto('/', { waitUntil: 'networkidle' });
        await expect(page.getByRole('link', { name: /resident login|open resident portal/i }).first()).toBeVisible();

        const dimensions = await page.evaluate(() => ({
            clientWidth: document.documentElement.clientWidth,
            scrollWidth: document.documentElement.scrollWidth,
        }));
        expect(dimensions.scrollWidth).toBeLessThanOrEqual(dimensions.clientWidth + 1);
    });
}

test('reduced-motion preference keeps content available', async ({ page }) => {
    await page.emulateMedia({ reducedMotion: 'reduce' });
    await page.goto('/', { waitUntil: 'networkidle' });
    await expect(page.locator('#contact')).toBeAttached();
    await expect(page.getByRole('button', { name: 'Send message' })).toBeVisible();
});
