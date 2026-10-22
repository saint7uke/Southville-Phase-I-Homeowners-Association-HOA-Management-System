import { expect, test } from '@playwright/test';

test('admin complaint creation is forbidden under the homeowner-only submission policy', async ({ page }) => {
    test.setTimeout(90_000);
    const email = process.env.HOA_BROWSER_ADMIN_EMAIL;
    const password = process.env.HOA_BROWSER_ADMIN_PASSWORD;
    test.skip(!email || !password, 'Requires designated Admin browser-test credentials.');
    await page.goto('/login');
    await page.getByLabel('Email address').fill(email);
    await page.locator('input[type="password"]').fill(password);
    await page.locator('button[type="submit"]').click();
    await expect(page).toHaveURL(/\/admin\/?$/, { timeout: 60_000 });
    const response = await page.goto('/admin/complaints/create');
    expect(response.status()).toBe(403);
    await expect(page.getByLabel('Subject')).toHaveCount(0);
});
