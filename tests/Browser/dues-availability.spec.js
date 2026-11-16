import { expect as baseExpect, test } from '@playwright/test';

const expect = baseExpect.configure({ timeout: 60_000 });

for (const width of [1280, 390]) {
    test(`dues availability, keyboard selection and translations at ${width}px`, async ({ page, baseURL }) => {
        test.skip(process.env.HOA_PROJECT_BROWSER !== 'true', 'Requires isolated interface fixtures.');
        expect(new URL(baseURL).hostname).toBe('127.0.0.1');
        test.setTimeout(300_000);
        await page.setViewportSize({ width, height: 900 });
        // Forward each request once through Playwright's HTTP client: this host
        // emits Chromium network-change events even for loopback asset loads.
        // No retries (especially no payment or locale POST retries).
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
        if (await language.inputValue() !== 'en') {
            await language.selectOption('en');
            await expect(language).toBeEnabled();
        }
        await page.goto('/admin/payments/create');
        await expect(page.locator('html')).toHaveAttribute('data-locale-ready', 'true');
        const payer = page.getByRole('combobox', { name: /^Actual payer/ });
        await expect(payer).toBeDisabled();
        await expect(page.getByRole('link', { name: 'Review homeowner records', exact: true })).toBeVisible();
        const dues = page.locator('.fi-select-input-btn').first();
        await dues.focus();
        await dues.press('Enter');
        for (const [name, reason] of [
            [/Review Only.*outstanding/, 'No eligible payer—HOA review required'],
            [/Archived Review.*outstanding/, 'Archived homeowner—HOA review required'],
        ]) {
            const option = page.getByRole('option').filter({ hasText: name });
            await expect(option).toHaveAttribute('aria-disabled', 'true');
            await expect(option).toContainText(reason);
            await option.scrollIntoViewIfNeeded();
        }
        await page.screenshot({ path: `${process.env.HOA_PROJECT_REVIEW_DIR}/availability-${width}.png` });
        await page.getByRole('option').filter({ hasText: new RegExp(`Alert .*Installment fixture ${width}.*outstanding`) }).click();
        await expect(payer).toBeEnabled();
        await expect(payer.locator('option:checked')).toContainText('Owner');
        await page.getByRole('spinbutton', { name: /^Amount paid/ }).fill('25');
        await dues.click();
        await page.keyboard.press('Escape');
        await expect(page.getByRole('spinbutton', { name: /^Amount paid/ })).toHaveValue('25');
        await payer.focus();
        await expect(payer).toBeFocused();
        expect(await page.evaluate(() => document.documentElement.scrollWidth > innerWidth + 1)).toBe(false);
        await page.getByRole('combobox', { name: 'Language', exact: true }).selectOption('fil');
        await expect(page.locator('[data-locale-switcher] select')).toBeEnabled();
        await page.reload();
        await page.emulateMedia({ colorScheme: 'dark' });
        await page.locator('.fi-select-input-btn').first().click();
        await expect(page.getByRole('option').filter({ hasText: /Archived Review.*outstanding/ })).toContainText('Naka-archive ang residente—kailangang suriin ng HOA');
        await expect(page.getByRole('option').filter({ hasText: /Review Only.*outstanding/ })).toContainText('Walang kuwalipikadong magbabayad—kailangang suriin ng HOA');
        await page.screenshot({ path: `${process.env.HOA_PROJECT_REVIEW_DIR}/availability-fil-dark-${width}.png` });
        await page.keyboard.press('Escape');
        await language.selectOption('en');
        await expect(language).toBeEnabled();
    });
}
