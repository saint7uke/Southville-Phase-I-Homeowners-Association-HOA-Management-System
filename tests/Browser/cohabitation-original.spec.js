import AxeBuilder from '@axe-core/playwright';
import { expect, test } from '@playwright/test';

test('Cohabitation fields preserve registered details and missing-source gate accessibly', async ({ page, baseURL }) => {
    test.skip(process.env.HOA_BROWSER_COHABITATION_MISSING_SOURCE_FIXTURE !== 'true', 'Requires an isolated fixture with the Cohabitation source deliberately unavailable.');
    expect(new URL(baseURL).hostname).toMatch(/^(localhost|127\.0\.0\.1)$/);
    test.setTimeout(180_000);
    await page.goto('/login');
    await page.locator('#email').fill('admin@phase23.test');
    await page.locator('#password').fill('BrowserTest!2026');
    await page.locator('button[type="submit"]').click();
    await expect(page).toHaveURL(/\/admin\/?$/, { timeout: 60_000 });
    await page.goto('/admin/certificates/create');
    await page.getByRole('combobox', { name: /^Type/ }).selectOption('Certificate of Cohabitation');
    await expect(page.getByText('Awaiting corrected document', { exact: true })).toBeVisible({ timeout: 30_000 });
    await expect(page.getByText('Reviewed blank-field mapping for that PDF.', { exact: true })).toBeVisible();
    const owner = page.getByRole('combobox', { name: /^Homeowner/ });
    await owner.click();
    await page.getByRole('option', { name: /Juan/ }).first().click();
    const firstName = page.getByRole('textbox', { name: /^First resident full name/ });
    await expect(firstName).toHaveValue(/Juan/, { timeout: 30_000 });
    await expect(firstName).toHaveAttribute('readonly');
    await expect(page.getByRole('textbox', { name: /^First resident birth date/ })).toHaveValue('1990-01-01');
    await expect(page.getByRole('textbox', { name: /^First resident birth date/ })).toHaveAttribute('readonly');
    await page.getByRole('textbox', { name: /^Second resident full name/ }).fill('Maria Santos');
    await page.getByRole('textbox', { name: /^Second resident birth date/ }).fill('1991-02-03');
    await page.getByRole('textbox', { name: /^Additional address detail/ }).fill('Verified test address');
    const years = page.getByRole('spinbutton', { name: /^Completed years living together/ });
    await years.fill('-1');
    await expect(page.getByText(/must be at least 0/)).toBeVisible({ timeout: 30_000 });
    await years.fill('5');
    await expect(page.getByText(/must be at least 0/)).toHaveCount(0, { timeout: 30_000 });
    await page.getByRole('combobox', { name: /^Requesting person/ }).selectOption('two');
    await page.getByRole('button', { name: 'Preview completed certificate', exact: true }).press('Enter');
    await expect(page.getByText('Awaiting corrected document', { exact: true })).toBeVisible();
    await expect(page.getByRole('button', { name: 'Issue certificate', exact: true })).toBeDisabled();
    await expect(page.getByRole('link', { name: 'Open completed preview', exact: true })).toHaveCount(0);
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
    await expect(firstName).toHaveValue(/Juan/);
    await expect(years).toHaveValue('5');
    const deniedSource = await page.request.get('/certificates/cohabitation-source/review');
    expect(deniedSource.status()).toBe(409);
    expect(await deniedSource.text()).toContain('Back to Certificates');
    expect(await deniedSource.text()).not.toContain('Stack Trace');
});

test('Cohabitation setup explains missing files without offering unusable review or confirmation', async ({ page, baseURL }) => {
    test.skip(process.env.HOA_BROWSER_COHABITATION_MISSING_SOURCE_FIXTURE !== 'true', 'Requires an isolated fixture with the Cohabitation source deliberately unavailable.');
    expect(new URL(baseURL).hostname).toMatch(/^(localhost|127\.0\.0\.1)$/);
    test.setTimeout(180_000);
    await page.goto('/login');
    await page.locator('#email').fill('admin@phase23.test');
    await page.locator('#password').fill('BrowserTest!2026');
    await page.locator('button[type="submit"]').click();
    await expect(page).toHaveURL(/\/admin\/?$/, { timeout: 60_000 });
    await page.goto('/admin/certificates');
    const setup = page.getByRole('button', { name: 'Document setup', exact: true });
    await setup.click();
    await page.getByRole('button', { name: /Original Cohabitation document.*Awaiting corrected document/ }).click();
    const dialog = page.getByRole('dialog', { name: 'Original Cohabitation document', exact: true });
    // Filament's absolute-positioning dialog wrapper has no box; its window is visible.
    const modalWindow = dialog.locator('.fi-modal-window');
    await expect(modalWindow).toBeVisible({ timeout: 30_000 });
    await expect(dialog).toContainText('Awaiting corrected document');
    await expect(dialog).toContainText('Corrected original DOCX containing Phase 1 - ____.');
    await expect(dialog.getByRole('link', { name: 'Review original Cohabitation document', exact: true })).toHaveCount(0);
    await expect(dialog.getByRole('button', { name: 'Confirm source', exact: true })).toHaveCount(0);
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
    await dialog.getByRole('button', { name: 'Close', exact: true }).last().focus();
    await page.keyboard.press('Enter');
    await expect(modalWindow).not.toBeVisible({ timeout: 30_000 });
    await page.goto('/certificates/cohabitation-source/review');
    await expect(page.getByRole('heading', { name: 'Original Cohabitation document' })).toBeVisible();
    await page.getByRole('link', { name: 'Back to Certificates', exact: true }).press('Enter');
    await expect(page).toHaveURL(/\/admin\/certificates$/);
});
