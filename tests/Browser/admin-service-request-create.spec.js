import AxeBuilder from '@axe-core/playwright';
import { expect, test } from '@playwright/test';

const viewports = [
    { width: 320, height: 700 },
    { width: 768, height: 1024 },
    { width: 1440, height: 900 },
];

test('admin service request creation form is accessible and responsive', async ({ page }) => {
    test.setTimeout(90_000);

    const email = process.env.HOA_BROWSER_ADMIN_EMAIL;
    const password = process.env.HOA_BROWSER_ADMIN_PASSWORD;
    test.skip(!email || !password, 'Requires designated Admin browser-test credentials.');

    await page.goto('/admin/login', { waitUntil: 'networkidle' });
    await page.getByLabel('Email address').fill(email);
    await page.locator('input[type="password"]').fill(password);
    await page.locator('button[type="submit"]').click();
    await expect(page).toHaveURL(/\/admin\/?$/, { timeout: 60_000 });

    await page.goto('/admin/service-requests/create', { waitUntil: 'networkidle' });
    await expect(page.getByRole('heading', { name: /create service request/i })).toBeVisible();
    await expect(page.getByLabel('Request type')).toBeVisible();
    await expect(page.getByLabel('Subject')).toBeVisible();
    await expect(page.getByLabel('Description')).toBeVisible();
    await expect(page.getByText('New service requests enter the workflow as Pending.')).toBeVisible();
    await expect(page.getByText('Upload up to 3 PDF, image, or Word files; 5 MB each.')).toBeVisible();

    const accessibility = await new AxeBuilder({ page })
        .withTags(['wcag2a', 'wcag2aa', 'wcag21aa', 'wcag22aa'])
        .analyze();
    expect(accessibility.violations.filter((violation) => ['serious', 'critical'].includes(violation.impact))).toEqual([]);

    for (const viewport of viewports) {
        await page.setViewportSize(viewport);
        await expect(page.getByLabel('Subject')).toBeVisible();

        const dimensions = await page.evaluate(() => ({
            clientWidth: document.documentElement.clientWidth,
            scrollWidth: document.documentElement.scrollWidth,
        }));
        expect(dimensions.scrollWidth, `Service request form overflow at ${viewport.width}px`).toBeLessThanOrEqual(dimensions.clientWidth + 1);
    }
});
