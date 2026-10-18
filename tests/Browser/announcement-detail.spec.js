import AxeBuilder from '@axe-core/playwright';
import { expect, test } from '@playwright/test';

const viewports = [
    { width: 320, height: 700 },
    { width: 768, height: 1024 },
    { width: 1440, height: 900 },
    { width: 1920, height: 1080 },
];

test('homeowner announcement detail is accessible and responsive', async ({ page }) => {
    test.setTimeout(90_000);

    const email = process.env.HOA_BROWSER_HOMEOWNER_EMAIL;
    const password = process.env.HOA_BROWSER_HOMEOWNER_PASSWORD;
    test.skip(!email || !password, 'Requires designated Homeowner browser-test credentials.');

    await page.goto('/homeowner/login', { waitUntil: 'networkidle' });
    await page.getByLabel('Email address').fill(email);
    await page.locator('input[type="password"]').fill(password);
    await page.locator('button[type="submit"]').click();
    await expect(page).toHaveURL(/\/homeowner\/?$/, { timeout: 60_000 });

    const configuredAnnouncementId = process.env.HOA_BROWSER_ANNOUNCEMENT_ID;
    if (configuredAnnouncementId) {
        await page.goto(`/homeowner/announcements/${configuredAnnouncementId}`, { waitUntil: 'networkidle' });
    } else {
        await page.goto('/homeowner/announcements', { waitUntil: 'networkidle' });
        await page.getByRole('link', { name: 'View' }).first().click();
    }

    const article = page.locator('.hoa-announcement-detail__article');
    await expect(article).toBeVisible();
    await expect(page.getByRole('link', { name: 'Back to announcements' })).toBeVisible();
    await expect(article.getByRole('heading', { level: 2 })).toBeVisible();
    await expect(article.locator('time')).toBeVisible();

    const accessibility = await new AxeBuilder({ page })
        .withTags(['wcag2a', 'wcag2aa', 'wcag21aa', 'wcag22aa'])
        .analyze();
    expect(accessibility.violations.filter((violation) => ['serious', 'critical'].includes(violation.impact))).toEqual([]);

    for (const viewport of viewports) {
        await page.setViewportSize(viewport);
        await expect(article).toBeVisible();

        const dimensions = await page.evaluate(() => ({
            clientWidth: document.documentElement.clientWidth,
            scrollWidth: document.documentElement.scrollWidth,
        }));
        expect(dimensions.scrollWidth, `Announcement detail overflow at ${viewport.width}px`).toBeLessThanOrEqual(dimensions.clientWidth + 1);
    }
});
