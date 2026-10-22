import AxeBuilder from '@axe-core/playwright';
import { expect, test } from '@playwright/test';

test.beforeEach(async ({ page, baseURL }) => {
    test.skip(process.env.HOA_BROWSER_COHABITATION_PHASE_FIXTURE !== 'true', 'Requires the guarded CohabitationPhaseBrowserSeeder and original Arial font.');
    expect(new URL(baseURL).hostname).toMatch(/^(localhost|127\.0\.0\.1)$/);
    test.setTimeout(180_000);
    await page.goto('/login');
    await page.locator('#email').fill('admin@phase23.test');
    await page.locator('#password').fill('BrowserTest!2026');
    await page.locator('button[type="submit"]').click();
    await expect(page).toHaveURL(/\/admin\/?$/, { timeout: 60_000 });
});

test('corrected original is privately reviewable with one-time source/signatory confirmation', async ({ page }) => {
    test.setTimeout(180_000);
    await page.goto('/admin/certificates');
    await page.getByRole('button', { name: 'Document setup', exact: true }).click();
    await page.getByRole('button', { name: /Original Cohabitation document.*Ready for review/ }).click();
    const dialog = page.getByRole('dialog', { name: 'Original Cohabitation document', exact: true });
    await expect(dialog.locator('.fi-modal-window')).toBeVisible({ timeout: 60_000 });
    await expect(dialog).toContainText('Ready for review');
    await expect(dialog).toContainText('JOSEPHINE D. ALARZAR');
    const source = await page.request.get(await dialog.getByRole('link', { name: 'Review original Cohabitation document', exact: true }).getAttribute('href'));
    expect(source.status()).toBe(200);
    expect(source.headers()['cache-control']).toContain('no-store');
    expect((await source.body()).subarray(0, 4).toString()).toBe('%PDF');
    for (const width of [320, 768, 1440]) {
        await page.setViewportSize({ width, height: 1000 });
        expect(await page.evaluate(() => document.documentElement.scrollWidth > innerWidth + 1)).toBe(false);
    }
    for (const colorScheme of ['light', 'dark']) {
        await page.emulateMedia({ colorScheme });
        await page.evaluate(theme => window.dispatchEvent(new CustomEvent('theme-changed', { detail: theme })), colorScheme);
        if (colorScheme === 'dark') await expect(page.locator('html')).toHaveClass(/dark/);
        else await expect(page.locator('html')).not.toHaveClass(/dark/);
        const result = await new AxeBuilder({ page }).withTags(['wcag2a', 'wcag2aa']).analyze();
        expect(result.violations.filter(v => ['serious', 'critical'].includes(v.impact))).toEqual([]);
    }
    await dialog.getByRole('button', { name: 'Close', exact: true }).last().press('Enter');
    await expect(dialog.locator('.fi-modal-window')).not.toBeVisible({ timeout: 30_000 });
});

test('registered phase autofills the corrected original through preview, issuance and the saved PDF', async ({ page }) => {
    test.setTimeout(180_000);
    await page.goto('/admin/certificates');
    await page.getByRole('button', { name: 'Document setup', exact: true }).click();
    await page.getByRole('button', { name: /Original Cohabitation document.*Ready for review/ }).click();
    const dialog = page.getByRole('dialog', { name: 'Original Cohabitation document', exact: true });
    await expect(dialog.locator('.fi-modal-window')).toBeVisible({ timeout: 60_000 });
    await dialog.getByRole('switch', { name: /I reviewed the uploaded document/ }).click();
    await dialog.getByRole('switch', { name: /The HOA authorizes the signatory/ }).click();
    await dialog.getByRole('textbox', { name: /Approval evidence/ }).fill('ISOLATED BROWSER TEST ONLY; this is not real HOA approval.');
    await dialog.getByRole('button', { name: 'Confirm source', exact: true }).click();
    await expect(dialog.locator('.fi-modal-window')).not.toBeVisible({ timeout: 30_000 });
    await page.reload();
    await page.getByRole('button', { name: 'Document setup', exact: true }).click();
    await expect(page.getByRole('button', { name: /Original Cohabitation document.*Ready for review/ })).toHaveCount(0);
    await page.goto('/admin/certificates/create');
    await page.getByRole('combobox', { name: /^Type/ }).selectOption('Certificate of Cohabitation');
    await page.getByRole('combobox', { name: /^Homeowner/ }).click();
    await page.getByRole('option', { name: /Juan Dela Cruz/ }).click();
    await expect(page.getByRole('textbox', { name: /^First resident full name/ })).toHaveValue('Juan Dela Cruz', { timeout: 30_000 });
    await expect(page.getByRole('textbox', { name: /^First resident full name/ })).toHaveAttribute('readonly');
    await expect(page.getByText('Phase 1 - B', { exact: true })).toBeVisible();
    await expect(page.getByRole('textbox', { name: /^Additional address detail/ })).toHaveCount(0);
    await page.getByRole('textbox', { name: /^Second resident full name/ }).fill('Maria Santos');
    await page.getByRole('textbox', { name: /^Second resident birth date/ }).fill('1991-02-03');
    const years = page.getByRole('spinbutton', { name: /^Completed years living together/ });
    await years.fill('-1');
    await expect(page.getByText(/must be at least 0/)).toBeVisible({ timeout: 30_000 });
    await years.fill('5');
    await expect(page.getByText(/must be at least 0/)).toHaveCount(0, { timeout: 30_000 });
    await page.getByRole('button', { name: 'Preview completed certificate', exact: true }).press('Enter');
    const preview = page.getByRole('link', { name: 'Open completed preview', exact: true });
    await expect(preview).toBeVisible({ timeout: 30_000 });
    expect((await page.request.get(await preview.getAttribute('href'))).status()).toBe(200);
    await expect(page.getByRole('button', { name: 'Issue certificate', exact: true })).toBeEnabled();
    await page.getByRole('button', { name: 'Issue certificate', exact: true }).click();
    await expect(page).toHaveURL(/\/admin\/certificates$/, { timeout: 60_000 });
    const row = page.getByRole('row').filter({ hasText: 'Juan Dela Cruz' });
    await expect(row).toContainText('Certificate of Cohabitation');
    const view = await page.request.get(await row.getByRole('link', { name: 'View / Print', exact: true }).getAttribute('href'));
    const download = await page.request.get(await row.getByRole('link', { name: 'Download', exact: true }).getAttribute('href'));
    expect(view.status()).toBe(200);
    expect(download.status()).toBe(200);
    expect(await view.body()).toEqual(await download.body());
});
