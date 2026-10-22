import AxeBuilder from '@axe-core/playwright';
import { expect, test } from '@playwright/test';

test('Payments and Certificates print and download the same saved original monthly receipt', async ({ page, baseURL }) => {
    test.skip(process.env.HOA_BROWSER_SHARED_RECEIPTS_FIXTURE !== 'true' || process.env.HOA_BROWSER_BUTAW_FONT_AVAILABLE !== 'true', 'Requires isolated shared-receipt fixtures and the licensed original font.');
    expect(new URL(baseURL).hostname).toMatch(/^(localhost|127\.0\.0\.1)$/);
    test.setTimeout(360_000);
    const script = await page.request.get('/js/filament/actions/actions.js');
    expect(script.ok()).toBeTruthy();
    expect(script.headers()['content-type']).toMatch(/javascript/);
    const goto = async (url) => {
        const response = await page.goto(url, { waitUntil: 'domcontentloaded' });
        expect(response.status()).toBe(200);
    };
    await goto('/login');
    await page.locator('#email').fill('admin@phase23.test');
    await page.locator('#password').fill('BrowserTest!2026');
    await page.locator('button[type="submit"]').click();
    await expect(page).toHaveURL(/\/admin\/?$/, { timeout: 60_000 });
    await goto('/admin/payments');
    let row = page.getByRole('row').filter({ hasText: '12.34' });
    const generate = row.getByRole('link', { name: 'Generate monthly receipt', exact: true });
    await expect(generate).toBeVisible({ timeout: 30_000 });
    const shortcut = new URL(await generate.getAttribute('href'));
    expect(shortcut.searchParams.get('document')).toBe('butaw');
    const paymentId = shortcut.searchParams.get('payment');
    await generate.click();
    await expect(page.getByRole('combobox', { name: /^Document/ })).toHaveValue('butaw', { timeout: 30_000 });
    const details = page.getByRole('region', { name: 'Automatically filled receipt details', exact: true });
    await expect(details).toContainText('Juan');
    await expect(details).toContainText('Tenant');
    await expect(details).toContainText('PHP 12.34');
    await expect(details).toContainText('October 2026');
    const previewButton = page.getByRole('button', { name: 'Preview monthly dues receipt', exact: true });
    await expect(previewButton).toBeEnabled({ timeout: 30_000 });
    await previewButton.press('Enter');
    const preview = page.getByRole('link', { name: 'Open receipt preview', exact: true });
    await expect(preview).toBeVisible({ timeout: 60_000 });
    expect((await page.request.get(await preview.getAttribute('href'))).ok()).toBeTruthy();
    for (const width of [320, 768, 1440]) {
        await page.setViewportSize({ width, height: 1000 });
        expect(await page.evaluate(() => document.documentElement.scrollWidth > innerWidth + 1)).toBe(false);
        await expect(details).toBeVisible();
    }
    for (const colorScheme of ['light', 'dark']) {
        await page.emulateMedia({ colorScheme });
        const result = await new AxeBuilder({ page }).withTags(['wcag2a', 'wcag2aa']).analyze();
        expect(result.violations.filter(v => ['serious', 'critical'].includes(v.impact))).toEqual([]);
    }
    const confirmationResponse = page.waitForResponse(response => response.url().includes('/livewire/update')
        && response.request().postData()?.includes('generateReceipt'), { timeout: 60_000 });
    await page.getByRole('button', { name: 'Generate monthly dues receipt', exact: true }).click();
    expect((await confirmationResponse).ok()).toBeTruthy();
    const dialog = page.getByRole('alertdialog');
    // Filament's role wrapper is zero-height; its fixed-position window is visible.
    await expect(dialog.locator('.fi-modal-window')).toBeVisible({ timeout: 30_000 });
    await expect.poll(() => dialog.evaluate(element => element.contains(document.activeElement))).toBe(true);
    const modal = await new AxeBuilder({ page }).withTags(['wcag2a', 'wcag2aa']).analyze();
    expect(modal.violations.filter(v => ['serious', 'critical'].includes(v.impact))).toEqual([]);
    await dialog.getByRole('button', { name: 'Confirm', exact: true }).click();
    await expect(page.getByText('Monthly dues receipt is ready.', { exact: true })).toBeVisible({ timeout: 60_000 });
    const documentUrl = await page.getByRole('link', { name: 'Download', exact: true }).getAttribute('href');
    const issued = await page.request.get(documentUrl);
    expect(issued.ok()).toBeTruthy();
    const bytes = await issued.body();
    expect(bytes.subarray(0, 5).toString()).toBe('%PDF-');
    await goto('/admin/payments');
    row = page.getByRole('row').filter({ hasText: '12.34' });
    await expect(row.getByRole('link', { name: 'Download', exact: true })).toHaveAttribute('href', documentUrl);
    await expect(row.getByRole('link', { name: 'Generate monthly receipt', exact: true })).toHaveCount(0);
    const printable = await page.request.get(await row.getByRole('link', { name: 'View / Print', exact: true }).getAttribute('href'));
    const legacy = await page.request.get(`/payments/${paymentId}/receipt`);
    expect(printable.headers()['content-disposition']).toContain('inline');
    expect((await printable.body()).equals(bytes)).toBeTruthy();
    expect((await legacy.body()).equals(bytes)).toBeTruthy();
    await goto('/admin/certificates');
    await page.getByRole('button', { name: 'Monthly receipts', exact: true }).click();
    const history = page.getByRole('dialog', { name: 'Saved monthly dues receipts', exact: true });
    await expect(history.locator('.fi-modal-window')).toBeVisible({ timeout: 30_000 });
    await history.getByRole('combobox', { name: /Monthly dues receipt/ }).click();
    await page.getByRole('option', { name: /Current receipt/ }).first().click();
    await expect(history.getByRole('link', { name: 'Download', exact: true })).toHaveAttribute('href', documentUrl, { timeout: 30_000 });
    await expect(history.getByRole('link', { name: 'View / Print', exact: true })).toHaveAttribute('href', `${documentUrl}?view=1`);
});
