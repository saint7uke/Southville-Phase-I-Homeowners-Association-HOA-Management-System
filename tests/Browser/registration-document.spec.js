import { test, expect } from '@playwright/test';
import path from 'node:path';

for (const width of [1280, 390]) {
    test(`registration document required at ${width}px`, async ({ page, baseURL }) => {
        test.skip(process.env.HOA_PROJECT_BROWSER !== 'true', 'Requires isolated fixtures.');
        test.setTimeout(180_000);
        await page.setViewportSize({ width, height: 900 });
        await page.route('**/*', route => new URL(route.request().url()).origin === new URL(baseURL).origin ? route.continue() : route.abort());
        let submissions = 0;
        page.on('request', request => { if (request.method() === 'POST' && new URL(request.url()).pathname === '/homeowner/register') submissions++; });
        await page.goto('/homeowner/register');
        await page.locator('[data-role-card="Owner"]').click();
        for (const [id, value] of Object.entries({ first_name: 'Synthetic', last_name: 'Applicant', date_of_birth: '1990-01-01', contact_number: '09171234567', email: 'document-fixture@example.test', password: 'ResidentPass!2026', password_confirmation: 'ResidentPass!2026', block: '98', lot: '97', residency_date: '2020-01-01' })) await page.locator(`#${id}`).fill(value);
        await page.locator('#phase_suffix').selectOption('A');
        await page.locator('#sex').selectOption('Female');
        await page.locator('[name="privacy_consent"]').check();
        const upload = page.locator('#verification_document');
        await expect(upload).toHaveAttribute('required', '');
        await page.getByRole('button', { name: 'Submit application', exact: true }).click();
        await expect(upload).toBeFocused();
        await expect(page.locator('#verification_document-realtime-error')).toContainText('Upload a verification document');
        expect(submissions).toBe(0);
        for (const locale of ['en', 'fil']) {
            await page.locator('[data-locale-switcher] select').selectOption(locale);
            await expect(page.locator('html')).toHaveAttribute('lang', locale);
            await expect(page.locator('[data-locale-switcher] select')).toBeEnabled();
            await expect(page.locator('#verification-document-hint')).toContainText(locale === 'en' ? 'maximum 10 MB' : 'hanggang 10 MB');
            await upload.setInputFiles({ name: 'bad.txt', mimeType: 'text/plain', buffer: Buffer.from('not a document') });
            await expect(upload).toHaveAttribute('aria-invalid', 'true');
            await upload.setInputFiles({ name: 'proof.pdf', mimeType: 'application/pdf', buffer: Buffer.from('%PDF-1.4 synthetic document') });
            await expect(upload).toHaveAttribute('aria-invalid', 'false');
            await upload.setInputFiles([]);
            await expect(upload).toHaveAttribute('aria-invalid', 'true');
            await expect(page.locator('#verification_document-realtime-error')).toContainText(locale === 'en' ? 'Upload a verification document' : 'Mag-upload ng dokumento');
        }
        await upload.scrollIntoViewIfNeeded();
        expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1)).toBeTruthy();
        await page.screenshot({ path: path.join(process.env.HOA_PROJECT_REVIEW_DIR, `required-document-${width}.png`) });
        expect(submissions).toBe(0);
    });
}
