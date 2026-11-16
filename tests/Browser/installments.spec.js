import { expect as baseExpect, test } from '@playwright/test';

const expect = baseExpect.configure({ timeout: 60_000 });

for (const width of [1280, 390]) {
    test(`installment dialog, cancellation, settlement and history at ${width}px`, async ({ page, baseURL }) => {
        test.skip(process.env.HOA_PROJECT_BROWSER !== 'true', 'Requires isolated interface fixtures.');
        expect(new URL(baseURL).hostname).toBe('127.0.0.1');
        test.setTimeout(360_000);
        await page.setViewportSize({ width, height: 900 });
        // Forward once to the real disposable HTTP server; avoid this Windows
        // host's Chromium network-change interruptions, without POST retries.
        await page.route('**/*', async route => {
            if (new URL(route.request().url()).origin !== new URL(baseURL).origin) return route.abort();
            await route.fulfill({ response: await route.fetch({ maxRedirects: 0, maxRetries: 0, timeout: 180_000 }) });
        });
        await page.goto('/login');
        await page.locator('#email').fill('admin@alerts.example.test');
        await page.locator('#password').fill('AlertFixture!2026');
        await page.locator('button[type="submit"]').click();
        await expect(page).toHaveURL(/\/admin\/?$/, { timeout: 60_000 });
        const language = page.locator('[data-locale-switcher] select');
        await expect(page.locator('html')).toHaveAttribute('data-locale-ready', 'true');
        if (await language.inputValue() !== 'en') {
            await language.selectOption('en');
            await expect(language).toBeEnabled();
        }
        await page.goto('/admin/payments/create');
        const payer = page.getByRole('combobox', { name: /^Actual payer/ });
        await expect(payer).toBeDisabled();
        const dues = page.locator('.fi-select-input-btn').first();
        await dues.click();
        await page.getByText(new RegExp(`Alert .*Installment fixture ${width}.*outstanding`)).last().click();
        await expect(payer).toBeEnabled();
        await expect(payer.locator('option:checked')).toContainText('Owner');
        await expect(payer.locator('option:checked')).toContainText('Alert');
        await page.getByRole('spinbutton', { name: /^Amount paid/ }).fill('25');
        await dues.click();
        const unavailable = page.getByRole('option').filter({ hasText: /Review Only.*outstanding/ });
        await expect(unavailable).toContainText('No eligible payer—HOA review required');
        await expect(unavailable).toHaveAttribute('aria-disabled', 'true');
        const archived = page.getByRole('option').filter({ hasText: /Archived Review.*outstanding/ });
        await expect(archived).toContainText('Archived homeowner—HOA review required');
        await expect(archived).toHaveAttribute('aria-disabled', 'true');
        await page.screenshot({ path: `${process.env.HOA_PROJECT_REVIEW_DIR}/dues-availability-${width}.png` });
        await page.keyboard.press('Escape');
        await expect(page.getByRole('spinbutton', { name: /^Amount paid/ })).toHaveValue('25');
        await expect(payer.locator('option:checked')).toContainText('Owner');
        await payer.focus();
        await expect(payer).toBeFocused();
        await page.screenshot({ path: `${process.env.HOA_PROJECT_REVIEW_DIR}/payer-selection-${width}.png` });
        await page.goto('/admin/payments');
        const rows = page.getByRole('row').filter({ hasText: `Installment fixture ${width}` });
        const additional = rows.first().getByRole('button', { name: 'Record additional payment', exact: true });
        await expect(additional).toBeVisible();
        const box = await additional.boundingBox();
        expect(box.width).toBeGreaterThanOrEqual(44);
        expect(box.height).toBeGreaterThanOrEqual(44);
        await additional.focus();
        await additional.press('Enter');
        const dialog = page.getByRole('dialog').filter({ has: page.getByRole('button', { name: 'Record payment', exact: true }) });
        await expect(dialog).toContainText('Available balance');
        const amount = width === 1280 ? '400.00' : '380.00';
        await expect(dialog).toContainText(`PHP ${amount}`);
        await dialog.getByRole('button', { name: 'Cancel', exact: true }).click();
        await expect(rows).toHaveCount(1);
        await additional.click();
        await dialog.getByRole('spinbutton', { name: /^Installment amount/ }).fill('0');
        await dialog.getByRole('button', { name: 'Record payment', exact: true }).click();
        await expect(dialog.locator('.fi-modal-window')).toBeVisible();
        await expect(rows).toHaveCount(1);
        await dialog.getByRole('spinbutton', { name: /^Installment amount/ }).fill(amount);
        await dialog.getByRole('button', { name: 'Record payment', exact: true }).click();
        await expect(page.locator('.swal2-popup')).toContainText('Additional Payment Recorded Successfully!', { timeout: 60_000 });
        await page.locator('.swal2-popup').getByRole('button', { name: 'OK', exact: true }).click();
        await expect(rows).toHaveCount(2);
        await expect(rows.getByRole('button', { name: 'Record additional payment', exact: true })).toHaveCount(0);
        await rows.first().getByRole('button', { name: 'Payment history', exact: true }).click();
        const history = page.getByRole('dialog').filter({ hasText: 'Each installment keeps its original status' });
        await expect(history).toContainText('PHP 500.00');
        await expect(history.locator('section')).toHaveCount(2);
        await expect(history).toContainText('Partial');
        await expect(history).toContainText('Paid');
        expect(await history.evaluate(element => element.scrollWidth > element.clientWidth + 1)).toBe(false);
        await page.screenshot({ path: `${process.env.HOA_PROJECT_REVIEW_DIR}/installments-${width}.png` });
        await page.keyboard.press('Escape');
        await expect(history.locator('.fi-modal-window')).not.toBeVisible();
        await page.getByRole('combobox', { name: 'Language', exact: true }).selectOption('fil');
        await expect(page.locator('html')).toHaveAttribute('lang', 'fil');
        await expect(page.locator('[data-locale-switcher] select')).toBeEnabled();
        // Livewire preserves its initial request locale in hydration. Verify server-rendered
        // Filipino copy on a fresh page without modifying that existing shared behavior.
        await page.reload();
        await rows.first().getByRole('button', { name: 'Kasaysayan ng pagbabayad', exact: true }).click();
        const translated = page.getByRole('dialog').filter({ hasText: 'Pinananatili ng bawat hulog' });
        await expect(translated.locator('.fi-modal-window')).toBeVisible();
        await expect(translated).toContainText('Kabuuang tinanggap na bayad');
        await page.emulateMedia({ colorScheme: 'dark' });
        await page.screenshot({ path: `${process.env.HOA_PROJECT_REVIEW_DIR}/installments-fil-dark-${width}.png` });
        await page.keyboard.press('Escape');
        await expect(translated.locator('.fi-modal-window')).not.toBeVisible();
        await page.goto('/admin/payments/create');
        await expect(page.getByText('Pumili muna ng dues bago piliin ang aktuwal na magbabayad.')).toBeVisible();
        await page.locator('.fi-select-input-btn').first().click();
        const translatedUnavailable = page.getByRole('option').filter({ hasText: /Review Only.*outstanding/ });
        await expect(translatedUnavailable).toHaveAttribute('aria-disabled', 'true');
        await expect(translatedUnavailable).toContainText('Walang kuwalipikadong magbabayad—kailangang suriin ng HOA');
        await page.screenshot({ path: `${process.env.HOA_PROJECT_REVIEW_DIR}/payer-selection-fil-${width}.png` });
        await page.getByRole('combobox').first().selectOption('en');
        await expect(page.locator('html')).toHaveAttribute('lang', 'en');
        await expect(page.locator('[data-locale-switcher] select')).toBeEnabled();
    });
}
