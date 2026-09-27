import AxeBuilder from '@axe-core/playwright';
import { expect, test } from '@playwright/test';

test('admin can preview filtered reports and return keyboard focus', async ({ page }) => {
    test.setTimeout(60_000);
    test.skip(!process.env.HOA_BROWSER_ADMIN_EMAIL || !process.env.HOA_BROWSER_ADMIN_PASSWORD,
        'Requires a designated local or isolated CI test administrator.');

    await page.goto('/admin/login', { waitUntil: 'domcontentloaded' });
    await page.getByLabel('Email address').fill(process.env.HOA_BROWSER_ADMIN_EMAIL);
    await page.locator('input[type="password"]').fill(process.env.HOA_BROWSER_ADMIN_PASSWORD);
    await page.getByRole('button', { name: 'Sign in', exact: true }).click();
    await expect(page).toHaveURL(/\/admin\/?$/, { timeout: 30_000 });
    await page.goto('/admin/reports', { waitUntil: 'domcontentloaded' });

    await expect(page.locator('link[href$="/css/hoa-reports.css"]')).toHaveCount(1);

    for (const viewport of [
        { width: 320, height: 720 },
        { width: 768, height: 900 },
        { width: 1365, height: 768 },
        { width: 1920, height: 1080 },
    ]) {
        await page.setViewportSize(viewport);
        const documentOverflows = await page.evaluate(() => document.documentElement.scrollWidth > window.innerWidth + 1);
        expect(documentOverflows, `page should not overflow at ${viewport.width}px`).toBe(false);
    }

    await page.getByLabel('Rows per page').selectOption('5');
    await expect(page.getByText('Showing 1–5 of 8 reports')).toBeVisible();
    await page.getByRole('button', { name: 'Page 2', exact: true }).click();
    await expect(page.getByText('Showing 6–8 of 8 reports')).toBeVisible();
    await page.getByRole('button', { name: 'Sort by report name descending' }).click();
    await expect(page.getByText('Showing 1–5 of 8 reports')).toBeVisible();
    await page.getByLabel('Rows per page').selectOption('10');

    const accessibility = await new AxeBuilder({ page })
        .withTags(['wcag2a', 'wcag2aa', 'wcag21aa', 'wcag22aa'])
        .analyze();
    expect(accessibility.violations.filter((violation) => ['serious', 'critical'].includes(violation.impact))).toEqual([]);
    const row = page.getByRole('row').filter({
        has: page.getByText('Homeowner Master List', { exact: true }),
    });
    await row.locator('select').selectOption('Inactive');
    const preview = row.getByRole('button', { name: 'Preview', exact: true });
    await preview.click();
    const dialog = page.getByRole('dialog', { name: /Homeowner Master List/ });
    await expect(dialog).toBeVisible();
    await expect(dialog.getByRole('columnheader', { name: 'Homeowner', exact: true })).toBeVisible();
    await page.keyboard.press('Escape');
    await expect(dialog).not.toBeVisible();
    await expect(preview).toBeFocused();
});
