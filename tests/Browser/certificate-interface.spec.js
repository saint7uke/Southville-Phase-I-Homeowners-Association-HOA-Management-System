import AxeBuilder from '@axe-core/playwright';
import { expect, test } from '@playwright/test';

test.beforeEach(async ({ baseURL }) => {
    test.skip(process.env.HOA_BROWSER_PHASE23_FIXTURE !== 'true', 'Requires the isolated seeded browser database.');
    expect(new URL(baseURL).hostname).toMatch(/^(localhost|127\.0\.0\.1)$/);
});

for (const role of ['admin', 'staff']) {
    test(`${role} certificate header separates daily actions from one-time setup`, async ({ page }) => {
        test.setTimeout(180_000);
        await page.goto('/login');
        await page.locator('#email').fill(`${role}@phase23.test`);
        await page.locator('#password').fill('BrowserTest!2026');
        await page.locator('button[type="submit"]').click();
        await expect(page).toHaveURL(new RegExp(`/${role}/?$`), { timeout: 60_000 });
        await page.goto(`/${role}/certificates`);
        await page.waitForLoadState('networkidle');
        const header = page.locator('.fi-header-actions-ctn');
        await expect(header.getByRole('link', { name: 'New document', exact: true })).toBeVisible();
        await expect(header.getByRole('button', { name: 'Monthly receipts', exact: true })).toBeVisible();
        await expect(header.getByRole('button', { name: 'Confirm official Residency source', exact: true })).not.toBeVisible();
        for (const width of [320, 768, 1440]) {
            await page.setViewportSize({ width, height: 1000 });
            const count = await header.locator('a:visible, button:visible').count();
            expect(count).toBe(role === 'admin' ? 3 : 2);
            expect(await page.evaluate(() => document.documentElement.scrollWidth > innerWidth + 1)).toBe(false);
        }
        const setup = header.getByRole('button', { name: 'Document setup', exact: true });
        if (role === 'admin') {
            await setup.click();
            await expect(page.getByRole('link', { name: 'Review uploaded Residency document', exact: true })).toBeVisible();
            const results = await new AxeBuilder({ page }).withTags(['wcag2a', 'wcag2aa']).analyze();
            expect(results.violations.filter(v => ['serious', 'critical'].includes(v.impact))).toEqual([]);
            await page.keyboard.press('Escape');
            await expect(setup).toBeFocused();
            await expect(page.getByRole('link', { name: 'Review uploaded Residency document', exact: true })).not.toBeVisible();
        } else {
            await expect(setup).toHaveCount(0);
        }
        const modalResponse = page.waitForResponse(response => response.url().includes('/livewire/update')
            && response.request().postData()?.includes('findPaymentReceipt'), { timeout: 60_000 });
        await page.getByRole('button', { name: 'Monthly receipts', exact: true }).click();
        expect((await modalResponse).ok()).toBeTruthy();
        const dialog = page.getByRole('dialog', { name: 'Saved monthly dues receipts', exact: true });
        if (await dialog.getByRole('combobox').count()) {
            await expect(dialog.getByRole('combobox')).toBeVisible({ timeout: 30_000 });
        } else {
            await expect(dialog).toContainText('No generated receipts yet');
            await expect(dialog.getByRole('link', { name: 'Create monthly dues receipt', exact: true })).toBeVisible();
        }
        await expect(dialog.getByRole('button', { name: /Confirm|Submit/ })).toHaveCount(0);
        const closeResponse = page.waitForResponse(response => response.url().includes('/livewire/update')
            && response.request().postData()?.includes('unmountAction'), { timeout: 60_000 });
        await dialog.getByRole('button', { name: 'Close', exact: true }).last().click();
        expect((await closeResponse).ok()).toBeTruthy();
    });
}
