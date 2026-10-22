import AxeBuilder from '@axe-core/playwright';
import { expect, test } from '@playwright/test';

// Never use these against an existing application database or arbitrary remote host.
test.beforeEach(async ({ baseURL, page }) => {
    test.setTimeout(180_000);
    test.skip(process.env.HOA_BROWSER_READINESS_FIXTURE !== 'true', 'Requires the guarded Phase23BrowserSeeder on a disposable SQLite database.');
    expect(new URL(baseURL).hostname).toMatch(/^(127\.0\.0\.1|localhost)$/);
    page.on('pageerror', error => console.error(`Application page error: ${error.message}`));
});

async function login(page, account, panel) {
    await page.goto('/login');
    await page.locator('#email').fill(`${account}@phase23.test`);
    await page.locator('#password').fill('BrowserTest!2026');
    await page.locator('button[type="submit"]').click();
    await expect(page).toHaveURL(new RegExp(`/${panel}/?$`), { timeout: 60_000 });
    await page.waitForLoadState('load');
    await expect(page.locator('html')).toHaveAttribute('data-locale-ready', 'true', { timeout: 30_000 });
    if (await page.locator('#hoa-locale').inputValue() !== 'en') {
        // Fixture setup, before any unsaved edits: clear preferences left by another test.
        // Dedicated locale cases below still exercise the real browser selector without reload.
        const csrf = await page.locator('meta[name="csrf-token"]').first().getAttribute('content');
        const reset = await page.request.post('/locale', { data: { locale: 'en' }, headers: { 'X-CSRF-TOKEN': csrf, Accept: 'application/json' }, timeout: 60_000 });
        expect(reset.ok()).toBeTruthy();
        await page.reload();
        await expect(page.locator('html')).toHaveAttribute('lang', 'en');
    }
}

test('Filipino realtime feedback changes language without erasing input or validation state', async ({ page }) => {
    await login(page, 'resident', 'homeowner');
    await page.goto('/homeowner/my-profile');
    const wrapper = page.locator('[data-profile-field-wrapper="contact_number"]');
    await wrapper.locator('input').fill('0917');
    await expect(wrapper.locator('[role="status"]')).toContainText('Enter an 11-digit');
    await page.locator('#hoa-locale').selectOption('fil');
    await expect(page.locator('html')).toHaveAttribute('lang', 'fil', { timeout: 30_000 });
    await expect(wrapper.locator('[role="status"]')).toContainText('Maglagay ng 11-digit', { timeout: 30_000 });
    await expect(wrapper.locator('input')).toHaveValue('0917');
    await expect(wrapper.locator('input')).toHaveAttribute('aria-invalid', 'true');
    await wrapper.locator('input').fill('09171234567');
    await expect(wrapper.locator('[role="status"]')).toContainText('Wasto ang inilagay.');
    await expect(wrapper.locator('input')).toHaveAttribute('aria-invalid', 'false');
    await page.locator('#hoa-locale').selectOption('en');
    await expect(wrapper.locator('[role="status"]')).toContainText('Looks good.');
});

test('admin payment form is cash-only and complaint creation remains forbidden', async ({ page }) => {
    await login(page, 'admin', 'admin');
    await page.goto('/admin/payments/create');
    const method = page.locator('input[wire\\:model="data.payment_method"]');
    await expect(method).toHaveValue('Cash');
    await expect(method).toHaveJSProperty('readOnly', true);
    const forbidden = await page.goto('/admin/complaints/create');
    expect(forbidden.status()).toBe(403);
});

test('admin generates Residency certificates entirely from the certificate resource', async ({ page }) => {
    test.skip(process.env.HOA_BROWSER_RESIDENCY_FONT_AVAILABLE !== 'true', 'Requires the licensed original Times New Roman Bold font on the isolated browser server; no substitute is used.');
    await login(page, 'admin', 'admin');
    expect((await page.goto('/admin/certificate-templates')).status()).toBe(404);
    await page.goto('/admin/certificates');
    await expect(page.getByRole('link', { name: 'New document', exact: true })).toBeVisible();
    await page.getByRole('button', { name: 'Document setup', exact: true }).click();
    const reference = page.getByRole('link', { name: 'Review uploaded Residency document' });
    const response = await page.request.get(await reference.getAttribute('href'));
    expect(response.ok()).toBeTruthy();
    expect(response.headers()['content-type']).toContain('application/pdf');
    expect(response.headers()['cache-control']).toContain('private');
    expect((await response.body()).subarray(0, 5).toString()).toBe('%PDF-');
    for (const width of [320, 768, 1365]) {
        await page.setViewportSize({ width, height: 900 });
        expect(await page.evaluate(() => document.documentElement.scrollWidth > window.innerWidth + 1)).toBe(false);
    }
    const confirmation = page.getByRole('button', { name: 'Confirm official Residency source', exact: true });
    if (await confirmation.isVisible()) {
        await confirmation.click();
        const dialog = page.getByRole('alertdialog', { name: 'Confirm official Residency source', exact: true });
        // Filament's outer modal root can have no layout box while its fixed window is visible.
        await expect(dialog.getByRole('switch').first()).toBeVisible({ timeout: 30_000 });
        await expect(dialog).toContainText('One-time setup');
        await dialog.getByRole('switch').nth(0).click();
        await dialog.getByRole('switch').nth(1).click();
        await dialog.getByLabel('Approval evidence / note').fill('Synthetic HOA approval for this isolated browser fixture only.');
        await dialog.getByRole('button', { name: 'Confirm source', exact: true }).click();
        await expect(page.getByText('Official Residency source confirmed', { exact: true })).toBeVisible({ timeout: 30_000 });
    }
    await page.reload();
    await page.getByRole('button', { name: 'Document setup', exact: true }).click();
    await expect(confirmation).toHaveCount(0);
    await page.goto('/admin/certificates/create');
    await expect(page.getByRole('button', { name: 'Issue certificate', exact: true })).toBeDisabled();
    await page.getByRole('combobox', { name: /Homeowner/ }).click();
    await page.getByRole('option', { name: /Juan.*Dela Cruz/ }).click();
    await page.getByLabel('Purpose', { exact: true }).fill('Barangay clearance');
    await page.getByRole('button', { name: 'Preview completed certificate', exact: true }).click();
    const previewLink = page.getByRole('link', { name: 'Open completed preview', exact: true });
    await expect(previewLink).toBeVisible({ timeout: 30_000 });
    const preview = await page.request.get(await previewLink.getAttribute('href'));
    expect(preview.ok()).toBeTruthy();
    expect(preview.headers()['cache-control']).toContain('no-store');
    for (const width of [320, 768, 1365]) {
        await page.setViewportSize({ width, height: 900 });
        expect(await page.evaluate(() => document.documentElement.scrollWidth > window.innerWidth + 1)).toBe(false);
    }
    const accessibility = await new AxeBuilder({ page }).withTags(['wcag2a', 'wcag2aa']).analyze();
    expect(accessibility.violations.filter(issue => ['serious', 'critical'].includes(issue.impact))).toEqual([]);
    await page.getByRole('button', { name: 'Issue certificate', exact: true }).click();
    await expect(page).toHaveURL(/\/admin\/certificates$/, { timeout: 60_000 });
    const viewLink = page.getByRole('link', { name: 'View / Print', exact: true }).first();
    await expect(viewLink).toBeVisible();
    const official = await page.request.get(await viewLink.getAttribute('href'));
    expect(official.ok()).toBeTruthy();
    expect(official.headers()['content-disposition']).toContain('inline');
    expect((await official.body()).subarray(0, 5).toString()).toBe('%PDF-');
});

test('Filipino report filtering keeps canonical statuses and unchanged complaint content', async ({ page }) => {
    await login(page, 'admin', 'admin');
    await page.goto('/admin/reports');
    await page.locator('#hoa-locale').selectOption('fil');
    await expect(page.locator('html')).toHaveAttribute('lang', 'fil', { timeout: 30_000 });
    // A fresh request must also render canonical option values in Filipino.
    await page.reload();
    const status = page.locator('#complaints-status');
    await expect(status.locator('option[value="Resolved"]')).toHaveText('Nalutas');
    await status.selectOption('Resolved');
    await expect(status).toHaveValue('Resolved');
    await page.locator('#report-preview-complaints').click();
    const dialog = page.getByRole('dialog').filter({ has: page.locator('#report-preview-title') });
    await expect(dialog).toBeVisible({ timeout: 30_000 });
    await expect(dialog).toContainText('Resolved browser concern');
    await expect(dialog).toContainText('Mga tumugmang tala');
    await page.keyboard.press('Escape');
    await expect(dialog).not.toBeVisible();
    await expect(page.locator('#report-preview-complaints')).toBeFocused();
});

test('project modal keeps draft input when language changes and supports keyboard cancellation', async ({ page }) => {
    await login(page, 'admin', 'admin');
    await page.goto('/admin/projects');
    const opened = page.waitForResponse(response => response.url().endsWith('/livewire/update') && response.request().postData()?.includes('mountAction'), { timeout: 60_000 });
    await page.getByRole('button', { name: 'Add project', exact: true }).click();
    expect((await opened).ok()).toBeTruthy();
    // Filament's dialog root can have no box while its fixed-position window is visible.
    const dialog = page.getByRole('dialog', { name: 'Add project', exact: true });
    // Address the stable input, not its label (the label intentionally changes language).
    const title = dialog.locator('input[type="text"]').first();
    await expect(title).toBeVisible({ timeout: 30_000 });
    await title.fill('Unpublished English resident content');
    // A focused modal traps keyboard input; the locale change is made through its existing selector.
    await page.locator('#hoa-locale').selectOption('fil', { force: true });
    await expect(page.locator('html')).toHaveAttribute('lang', 'fil', { timeout: 30_000 });
    await expect(title).toHaveValue('Unpublished English resident content');
    const accessibility = await new AxeBuilder({ page }).withTags(['wcag2a', 'wcag2aa']).analyze();
    expect(accessibility.violations.filter(issue => ['serious', 'critical'].includes(issue.impact))).toEqual([]);
    await page.keyboard.press('Escape');
    await expect(title).not.toBeVisible();
    await expect(page.getByRole('button', { name: /Add project|Magdagdag ng proyekto/ })).toBeFocused();
});

for (const account of ['resident', 'coowner', 'tenant']) {
    test(`${account} has a restricted read-only household summary`, async ({ page }) => {
        await login(page, account, 'homeowner');
        await page.goto('/homeowner/my-household');
        await expect(page.getByText('PROVISIONAL RBI', { exact: false })).toBeVisible();
        await expect(page.getByText('Unrelated Browser Fixture', { exact: false })).toHaveCount(0);
        await expect(page.getByRole('button', { name: /Add resident|Edit household/ })).toHaveCount(0);
        const forbidden = await page.goto('/admin/households');
        expect(forbidden.status()).toBe(403);
    });
}

test('Filipino contact submission succeeds once and clears the form without a false failure', async ({ page }) => {
    await page.goto('/');
    await expect(page.locator('html')).toHaveAttribute('data-locale-ready', 'true', { timeout: 30_000 });
    await page.locator('#hoa-locale').selectOption('fil');
    await expect(page.locator('html')).toHaveAttribute('lang', 'fil', { timeout: 30_000 });
    await page.locator('#contact').scrollIntoViewIfNeeded();
    await page.locator('#contact-name').fill('Synthetic English contact name');
    await page.locator('#contact-email').fill('contact@phase23.test');
    await page.locator('#contact-subject').fill('Synthetic browser localization review');
    await page.locator('#contact-message').fill('Please retain this English message unchanged.');
    const response = page.waitForResponse(result => new URL(result.url()).pathname === '/contact' && result.request().method() === 'POST');
    await page.locator('#contact button[type="submit"]').click();
    expect((await response).status()).toBe(201);
    await expect(page.locator('.hoa-contact-success')).toContainText('Salamat.');
    await expect(page.locator('.hoa-contact-error')).toHaveCount(0);
    await expect(page.locator('#contact-name')).toHaveValue('', { timeout: 15_000 });
});

for (const account of ['staff', 'unassigned']) {
    test(`${account} sees the correct finalized complaint controls`, async ({ page }) => {
        await login(page, account, 'staff');
        await page.goto('/staff/complaints/1');
        const reopen = page.getByRole('button', { name: 'Reopen complaint', exact: true });
        if (account === 'unassigned') {
            await expect(reopen).toHaveCount(0);
        } else {
            await expect(reopen).toBeVisible();
            const opened = page.waitForResponse(response => response.url().endsWith('/livewire/update') && response.request().postData()?.includes('mountAction'), { timeout: 60_000 });
            await reopen.click();
            expect((await opened).ok()).toBeTruthy();
            const dialog = page.getByRole('alertdialog', { name: 'Reopen resolved complaint', exact: true });
            await expect(dialog.getByRole('textbox', { name: 'Reason for reopening', exact: false })).toBeVisible({ timeout: 30_000 });
            await dialog.getByRole('textbox', { name: 'Reason for reopening', exact: false }).fill('Synthetic review reason, not an approval.');
            await dialog.getByRole('button', { name: 'Cancel', exact: true }).click();
            await expect(dialog).not.toBeVisible();
            await page.reload();
            await expect(reopen).toBeVisible();
        }
        const forbidden = await page.goto('/staff/complaints/1/edit');
        expect(forbidden.status()).toBe(403);
    });
}
