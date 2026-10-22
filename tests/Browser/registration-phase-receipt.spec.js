import AxeBuilder from '@axe-core/playwright';
import { expect, test } from '@playwright/test';

test.beforeEach(async ({ baseURL }) => {
    test.skip(process.env.HOA_BROWSER_PHASE23_FIXTURE !== 'true', 'Requires an isolated seeded browser database.');
    expect(new URL(baseURL).hostname).toMatch(/^(localhost|127\.0\.0\.1)$/);
});

for (const width of [320, 768, 1440]) {
    test(`registration labels, password states and Phase suffix are readable at ${width}px`, async ({ page }) => {
        test.setTimeout(90_000);
        await page.setViewportSize({ width, height: 1000 });
        await page.goto('/homeowner/register', { waitUntil: 'networkidle' });
        await page.locator('[data-role-card="Owner"]').click();
        await expect(page.locator('[name="house_number"]')).toHaveCount(0);
        await expect(page.locator('#phase-prefix')).toHaveText('Phase 1 -');
        await page.locator('#phase_suffix').fill('a7');
        await page.locator('#phase_suffix').blur();
        await expect(page.locator('#phase_suffix')).toHaveValue('A7');
        const labelColors = await page.locator('label[for="first_name"]').evaluate(label => ({
            label: getComputedStyle(label).color,
            text: getComputedStyle(label.querySelector('[data-i18n]')).color,
            required: getComputedStyle(label.querySelector('[aria-hidden]')).color,
        }));
        expect(labelColors.label).toBe(labelColors.text);
        expect(labelColors.required).not.toBe(labelColors.text);
        await page.locator('#password').fill('weak');
        await expect(page.locator('#password-requirements li.is-invalid')).not.toHaveCount(0);
        const text = page.locator('#password-requirements li > span[data-i18n]').first();
        expect(await text.evaluate(el => el.getBoundingClientRect().width)).toBeGreaterThan(100);
        expect(await text.evaluate(el => getComputedStyle(el).borderStyle)).toBe('none');
        await page.locator('#password').fill('SyntheticPass!2026');
        await expect(page.locator('#password-requirements li.is-valid')).toHaveCount(5);
        for (const colorScheme of ['light', 'dark']) {
            await page.emulateMedia({ colorScheme });
            const result = await new AxeBuilder({ page }).withTags(['wcag2a', 'wcag2aa', 'wcag21aa']).analyze();
            expect(result.violations.filter(v => ['serious', 'critical'].includes(v.impact))).toEqual([]);
        }
        expect(await page.evaluate(() => document.documentElement.scrollWidth > innerWidth + 1)).toBe(false);
    });
}

test('registration locale switching retains Phase, password and an unsaved upload', async ({ page }) => {
    test.setTimeout(90_000);
    await page.goto('/homeowner/register');
    await page.locator('[data-role-card="Owner"]').click();
    await page.locator('#phase_suffix').fill('a7');
    await page.locator('#first_name').fill('Unchanged');
    await page.locator('#password').fill('SyntheticPass!2026');
    await page.locator('#verification_document').setInputFiles({ name: 'synthetic-proof.pdf', mimeType: 'application/pdf', buffer: Buffer.from('%PDF-1.4 synthetic unsaved fixture') });
    await page.locator('#hoa-locale').selectOption('fil');
    await expect(page.locator('html')).toHaveAttribute('lang', 'fil');
    await expect(page.locator('#phase-help')).toContainText('Ilagay');
    await expect(page.locator('#first_name')).toHaveValue('Unchanged');
    await expect(page.locator('#password')).toHaveValue('SyntheticPass!2026');
    expect(await page.locator('#verification_document').evaluate(input => input.files[0].name)).toBe('synthetic-proof.pdf');
    await page.locator('#hoa-locale').selectOption('en');
    await expect(page.locator('#phase-help')).toContainText('Enter the phase suffix');
});

test('registration submits successfully without House Number and preserves the Phase in review', async ({ page }) => {
    test.setTimeout(120_000);
    await page.goto('/homeowner/register');
    await page.locator('[data-role-card="Owner"]').click();
    const stamp = String(Date.now());
    for (const [id, value] of Object.entries({ first_name: 'Synthetic', last_name: 'Applicant', phase_suffix: 'a7', street: 'Test Street', block: '77', lot: stamp,
        date_of_birth: '1990-01-01', contact_number: '09171234567', email: `registration-${stamp}@phase23.test`, password: 'SyntheticPass!2026', password_confirmation: 'SyntheticPass!2026', residency_date: '2020-01-01' })) {
        await page.locator(`#${id}`).fill(value);
    }
    await page.locator('#sex').selectOption('Male');
    await page.locator('#privacy_consent').check();
    await page.getByRole('button', { name: 'Submit application', exact: true }).click();
    const dialog = page.getByRole('dialog', { name: 'Review your application' });
    await expect(dialog).toContainText('Phase 1 - A7', { timeout: 30_000 });
    await dialog.getByRole('button', { name: 'Submit application', exact: true }).click();
    await expect(page).toHaveURL(/\/homeowner\/register\/submitted\?/, { timeout: 60_000 });
    await expect(page.getByText('Your application is under review', { exact: true })).toBeVisible();
    await page.reload();
    await expect(page.getByText('Your application is under review', { exact: true })).toBeVisible();
});

test('homeowner selection fills and generates the original Cash receipt inside Certificates', async ({ page }) => {
    test.skip(process.env.HOA_BROWSER_BUTAW_FONT_AVAILABLE !== 'true', 'Requires the exact original licensed Calibri font.');
    // Original-PDF review, Axe and two private downloads share one slow local PHP worker.
    test.setTimeout(480_000);
    await page.goto('/login');
    await page.locator('#email').fill('admin@phase23.test');
    await page.locator('#password').fill('BrowserTest!2026');
    await page.locator('button[type="submit"]').click();
    await expect(page).toHaveURL(/\/admin\/?$/, { timeout: 60_000 });
    await page.goto('/admin/payments/create');
    await page.getByRole('combobox', { name: /Dues obligation/ }).click();
    const obligationUpdate = page.waitForResponse(response => response.url().includes('/livewire/update')
        && response.request().postData()?.includes('dues_obligation_id'), { timeout: 60_000 });
    await page.getByRole('option', { name: /Juan.*Dela Cruz/ }).first().click();
    expect((await obligationUpdate).ok()).toBeTruthy();
    await expect(page.getByLabel('Billing period', { exact: true })).toHaveValue('October 2026', { timeout: 30_000 });
    await page.getByLabel('Amount paid', { exact: false }).fill('1.00');
    await page.getByLabel('Amount paid', { exact: false }).blur();
    await expect(page.getByLabel('Amount paid', { exact: false })).toHaveValue('1.00');
    await expect(page.locator('input[wire\\:model="data.payment_method"]')).toHaveValue('Cash');
    await page.getByRole('button', { name: 'Create', exact: true }).click();
    await expect(page.getByText('Payment recorded successfully', { exact: true })).toBeVisible({ timeout: 60_000 });
    await page.goto('/admin/certificates');
    await expect(page.getByRole('link', { name: 'New document', exact: true })).toBeVisible();
    await expect(page.getByRole('button', { name: 'Monthly receipts', exact: true })).toBeVisible();
    await page.getByRole('button', { name: 'Document setup', exact: true }).click();
    const sourceLink = page.getByRole('link', { name: 'Review original Resibo Butaw', exact: true });
    const reference = await page.request.get(await sourceLink.getAttribute('href'));
    expect(reference.ok()).toBeTruthy();
    expect(reference.headers()['content-type']).toContain('application/pdf');
    const confirmation = page.getByRole('button', { name: 'Confirm original Resibo Butaw source', exact: true });
    if (await confirmation.isVisible()) {
        const setupResponse = page.waitForResponse(response => response.url().includes('/livewire/update')
            && response.request().postData()?.includes('confirmButawSource'), { timeout: 60_000 });
        await confirmation.click();
        expect((await setupResponse).ok()).toBeTruthy();
        const sourceDialog = page.getByRole('alertdialog', { name: 'Confirm original Resibo Butaw source', exact: true });
        await expect(sourceDialog).toContainText('One-time setup', { timeout: 30_000 });
        await sourceDialog.getByRole('switch').click();
        await sourceDialog.getByLabel('Approval evidence / note').fill('Synthetic source review in an isolated browser fixture only; not a real HOA approval.');
        await sourceDialog.getByRole('button', { name: 'Confirm source', exact: true }).click();
        await expect(page.getByText('Original receipt source confirmed.', { exact: true })).toBeVisible({ timeout: 30_000 });
    }
    await page.reload();
    await page.getByRole('button', { name: 'Document setup', exact: true }).click();
    await expect(confirmation).toHaveCount(0);
    await page.goto('/admin/certificates/create');
    await page.getByRole('combobox', { name: /^Document/ }).selectOption('butaw', { timeout: 30_000 });
    await expect(page.getByLabel('Purpose', { exact: true })).not.toBeVisible();
    await page.getByRole('combobox', { name: /Homeowner \/ liable property account/ }).click();
    await page.locator('.fi-select-input-search-ctn input:visible').fill('Juan');
    await page.getByRole('option', { name: /Juan.*Dela Cruz/ }).last().click({ timeout: 30_000 });
    await page.getByRole('combobox', { name: /Accepted monthly Cash payment/ }).click();
    await page.getByRole('option', { name: /OR-.*October 2026.*PHP 1.00/ }).first().click({ timeout: 30_000 });
    const details = page.getByRole('region', { name: 'Automatically filled receipt details', exact: true });
    await expect(details).toContainText('Juan', { timeout: 30_000 });
    await expect(details).toContainText('October 2026');
    await expect(details).toContainText('PHP 1.00');
    await expect(details).toContainText('Admin Browser Fixture');
    const originalDetails = await details.locator('dd').allTextContents();
    await page.locator('#hoa-locale').selectOption('fil');
    await expect(page.getByRole('heading', { name: 'Mga awtomatikong inilagay na detalye ng resibo', exact: true })).toBeVisible();
    expect(await page.locator('#receipt-details-heading').locator('..').locator('dd').allTextContents()).toEqual(originalDetails);
    await page.locator('#hoa-locale').selectOption('en');
    await expect(details).toBeVisible();
    // A different liable account cannot retain payment details or an existing preview.
    await page.getByRole('combobox', { name: /Homeowner \/ liable property account/ }).click();
    await page.locator('.fi-select-input-search-ctn input:visible').fill('Unrelated');
    await page.getByRole('option', { name: /Unrelated.*Browser Fixture/ }).first().click({ timeout: 30_000 });
    await expect(page.getByText('This homeowner has no eligible recorded payment for the original Resibo Butaw.', { exact: false })).toBeVisible();
    await expect(details).toHaveCount(0);
    await expect(page.getByRole('button', { name: 'Preview monthly dues receipt', exact: true })).toBeDisabled();
    await page.getByRole('combobox', { name: /Homeowner \/ liable property account/ }).click();
    await page.locator('.fi-select-input-search-ctn input:visible').fill('Juan');
    await page.getByRole('option', { name: /Juan.*Dela Cruz/ }).first().click({ timeout: 30_000 });
    await page.getByRole('combobox', { name: /Accepted monthly Cash payment/ }).click();
    await page.getByRole('option', { name: /OR-.*October 2026.*PHP 1.00/ }).first().click({ timeout: 30_000 });
    const previewButton = page.getByRole('button', { name: 'Preview monthly dues receipt', exact: true });
    await expect(details).toContainText('PHP 1.00', { timeout: 30_000 });
    await expect(previewButton).toBeEnabled({ timeout: 30_000 });
    await previewButton.focus();
    await expect(previewButton).toBeFocused();
    await page.keyboard.press('Enter');
    const previewLink = page.getByRole('link', { name: 'Open receipt preview', exact: true });
    await expect(previewLink).toBeVisible({ timeout: 60_000 });
    const preview = await page.request.get(await previewLink.getAttribute('href'));
    expect(preview.ok()).toBeTruthy();
    expect(preview.headers()['cache-control']).toContain('no-store');
    for (const width of [320, 768, 1440]) {
        await page.setViewportSize({ width, height: 1000 });
        expect(await page.evaluate(() => document.documentElement.scrollWidth > innerWidth + 1)).toBe(false);
        await expect(details).toBeVisible();
    }
    await page.screenshot({ path: 'storage/framework/testing/homeowner-butaw-form-desktop.png', fullPage: true });
    const accessibility = await new AxeBuilder({ page }).withTags(['wcag2a', 'wcag2aa']).analyze();
    expect(accessibility.violations.filter(v => ['serious', 'critical'].includes(v.impact))).toEqual([]);
    await page.getByRole('button', { name: 'Generate monthly dues receipt', exact: true }).click();
    await expect(page.getByRole('alertdialog')).toBeVisible();
    const modalAccessibility = await new AxeBuilder({ page }).withTags(['wcag2a', 'wcag2aa']).analyze();
    expect(modalAccessibility.violations.filter(v => ['serious', 'critical'].includes(v.impact))).toEqual([]);
    await page.getByRole('alertdialog').getByRole('button', { name: 'Confirm', exact: true }).click();
    await expect(page.getByText('Monthly dues receipt is ready.', { exact: true })).toBeVisible({ timeout: 60_000 });
    const download = await page.request.get(await page.getByRole('link', { name: 'Download', exact: true }).getAttribute('href'));
    const printable = await page.request.get(await page.getByRole('link', { name: 'View / Print', exact: true }).getAttribute('href'));
    expect(download.ok()).toBeTruthy();
    expect(printable.headers()['content-disposition']).toContain('inline');
    expect((await download.body()).equals(await printable.body())).toBeTruthy();
});
