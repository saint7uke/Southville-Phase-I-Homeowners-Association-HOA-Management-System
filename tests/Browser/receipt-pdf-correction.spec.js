import AxeBuilder from '@axe-core/playwright';
import { createHash } from 'node:crypto';
import { expect, test } from '@playwright/test';

test('empty receipt history guides creation and reviews the exact single-slip uploaded PDF', async ({ page, baseURL }) => {
    test.skip(process.env.HOA_BROWSER_PHASE23_FIXTURE !== 'true', 'Requires an isolated, empty receipt-history fixture.');
    expect(new URL(baseURL).hostname).toMatch(/^(localhost|127\.0\.0\.1)$/);
    test.setTimeout(180_000);
    await page.goto('/login');
    await page.locator('#email').fill('admin@phase23.test');
    await page.locator('#password').fill('BrowserTest!2026');
    await page.locator('button[type="submit"]').click();
    await expect(page).toHaveURL(/\/admin\/?$/, { timeout: 60_000 });
    const certificateList = await page.goto('/admin/certificates');
    expect(certificateList.status()).toBe(200);
    await page.getByRole('button', { name: 'Monthly receipts', exact: true }).click();
    const dialog = page.getByRole('dialog', { name: 'Saved monthly dues receipts', exact: true });
    await expect(dialog).toContainText('No generated receipts yet', { timeout: 60_000 });
    await expect(dialog).toContainText('Recording a payment does not automatically create a Resibo Butaw PDF.');
    await expect(dialog.getByRole('combobox')).toHaveCount(0);
    const shortcut = dialog.getByRole('link', { name: 'Create monthly dues receipt', exact: true });
    await expect(shortcut).toHaveAttribute('href', /\/admin\/certificates\/create\?document=butaw$/);
    const a11y = await new AxeBuilder({ page }).withTags(['wcag2a', 'wcag2aa']).analyze();
    expect(a11y.violations.filter(v => ['serious', 'critical'].includes(v.impact))).toEqual([]);
    await shortcut.click();
    await expect(page.getByRole('combobox', { name: /^Document/ })).toHaveValue('butaw');
    await expect(page.getByLabel('Purpose', { exact: true })).not.toBeVisible();
    for (const width of [320, 768, 1440]) {
        await page.setViewportSize({ width, height: 1000 });
        expect(await page.evaluate(() => document.documentElement.scrollWidth > innerWidth + 1)).toBe(false);
    }
    await page.goto('/admin/certificates');
    await page.getByRole('button', { name: 'Document setup', exact: true }).click();
    const review = page.getByRole('link', { name: 'Review original Resibo Butaw', exact: true });
    const response = await page.request.get(await review.getAttribute('href'));
    expect(response.ok()).toBeTruthy();
    expect(response.headers()['cache-control']).toContain('private');
    expect(createHash('sha256').update(await response.body()).digest('hex')).toBe('ae0f3fb8ca5470aeebb97c22eec429536e0e71a7648ef0cf8f91cc389361dc30');
    await expect(page.getByRole('button', { name: 'Confirm original Resibo Butaw source', exact: true })).toBeVisible();
});
