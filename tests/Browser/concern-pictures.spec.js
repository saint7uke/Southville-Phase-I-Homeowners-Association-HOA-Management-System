import { test, expect } from '@playwright/test';
import path from 'node:path';

for (const width of [1280, 390]) {
    test(`private concern pictures at ${width}px`, async ({ page }) => {
        test.skip(process.env.HOA_PROJECT_BROWSER !== 'true', 'Requires isolated fixtures.');
        test.setTimeout(240_000);
        await page.setViewportSize({ width, height: 900 });
        page.on('response', response => {
            if (/\/case-attachments\/\d+\/preview$/.test(response.url())) console.log(`Synthetic preview: ${response.status()} ${response.headers()['content-type']}`);
        });
        await page.route('**/*', route => new URL(route.request().url()).origin === new URL(process.env.PLAYWRIGHT_BASE_URL).origin ? route.continue() : route.abort());
        await page.route('**/login', async route => {
            if (route.request().method() !== 'POST') return route.fallback();
            const response = await route.fetch({ maxRedirects: 0 });
            await route.fulfill({ response, headers: { ...response.headers(), location: new URL('/homeowner/my-concerns', process.env.PLAYWRIGHT_BASE_URL).href } });
        });
        await page.goto('/login');
        await page.getByLabel('Email address').fill('resident@alerts.example.test');
        await page.locator('input[type=password]').fill('AlertFixture!2026');
        await page.locator('button[type=submit]').click();
        await expect(page.locator('.swal2-popup')).toContainText('Login Successful!', { timeout: 60_000 });
        await page.locator('.swal2-confirm').click();
        const view = page.locator('.hoa-concern-list article').filter({ hasText: 'Picture preview fixture' }).getByRole('button');
        await view.click();
        const dialog = page.locator('.fi-modal-window:visible');
        const pictures = dialog.locator('[data-concern-image] img');
        await expect(pictures).toHaveCount(2);
        for (const picture of await pictures.all()) {
            await expect.poll(() => picture.evaluate(image => image.complete && image.naturalWidth > 0), { timeout: 60_000 }).toBeTruthy();
            const box = await picture.boundingBox();
            expect(box.width).toBeLessThanOrEqual(width - 30);
            expect(box.height).toBeLessThanOrEqual(540);
        }
        await expect(dialog.getByRole('link', { name: 'Download', exact: true })).toHaveCount(3);
        await page.screenshot({ path: path.join(process.env.HOA_PROJECT_REVIEW_DIR, `pictures-light-${width}.png`) });
        await page.evaluate(() => document.documentElement.classList.add('dark'));
        await page.screenshot({ path: path.join(process.env.HOA_PROJECT_REVIEW_DIR, `pictures-dark-${width}.png`) });
        await page.keyboard.press('Escape');
        await expect(view).toBeFocused();
        await page.evaluate(() => document.documentElement.classList.remove('dark'));
        await page.locator('[data-locale-switcher] select').selectOption('fil');
        await expect(page.locator('html')).toHaveAttribute('lang', 'fil', { timeout: 30_000 });
        await page.route('**/case-attachments/*/preview?simulate-failure=1', route => route.abort());
        await view.click();
        // Force a fresh failed request even when the reopened modal retains decoded images.
        await dialog.locator('img').first().evaluate(image => { image.src += '?simulate-failure=1'; });
        await expect(dialog.getByText('Hindi maipakita ang larawan. Maaari mo pa ring subukang i-download ang kalakip.', { exact: true }).first()).toBeVisible({ timeout: 60_000 });
        await expect(dialog.locator('img').first()).toHaveAttribute('alt', /^Kalakip na larawan:/);
        await expect(dialog.getByRole('link', { name: 'I-download', exact: true })).toHaveCount(3);
        await dialog.getByRole('button', { name: 'Isara', exact: true }).last().click();
        await expect(dialog).toHaveCount(0);
        await expect(page.locator('[data-concern-detail]')).toHaveCount(0);
        await page.locator('[data-locale-switcher] select').selectOption('en');
        await expect(page.locator('html')).toHaveAttribute('lang', 'en', { timeout: 30_000 });
        await expect(page.locator('[data-locale-switcher] select')).toBeEnabled();
    });
}
