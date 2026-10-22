import AxeBuilder from '@axe-core/playwright';
import { expect, test } from '@playwright/test';
import path from 'node:path';

test('canonical login switches language without losing credentials and public projects have a truthful empty state', async ({ page }) => {
    test.setTimeout(120_000);
    await page.goto('/login');
    await page.locator('#email').fill('unsaved@example.test');
    await page.locator('#password').fill('UnsavedPassword!2026');
    await page.locator('#hoa-locale').selectOption('fil');
    await expect(page.locator('html')).toHaveAttribute('lang', 'fil', { timeout: 30_000 });
    await expect(page.locator('#homeowner-login-title')).toHaveText('Mag-sign in');
    await expect(page.locator('#email')).toHaveValue('unsaved@example.test');
    await expect(page.locator('#password')).toHaveValue('UnsavedPassword!2026');
    await page.reload(); await expect(page.locator('html')).toHaveAttribute('lang', 'fil');
    await page.goto('/');
    await expect(page.locator('#projects')).toContainText('Wala pang nailalathalang natapos na proyekto.');
    await page.locator('#contact').evaluate(element => element.scrollIntoView({ behavior: 'instant', block: 'center' }));
    await expect(page.locator('#contact-name')).toBeVisible({ timeout: 15_000 });
    await page.locator('#contact-name').fill('Unsaved contact name');
    await page.locator('#contact-message').fill('Unsaved contact message');
    await page.locator('#hoa-locale').selectOption('en');
    await expect(page.locator('html')).toHaveAttribute('lang', 'en', { timeout: 30_000 });
    await expect(page.locator('#projects')).toContainText('No completed projects have been published yet.', { timeout: 15_000 });
    await expect(page.locator('#contact-name')).toHaveValue('Unsaved contact name');
    await expect(page.locator('#contact-message')).toHaveValue('Unsaved contact message');
});

test('profile retains unsaved state during language changes across responsive layouts', async ({ page }) => {
    test.setTimeout(120_000);
    test.skip(!process.env.HOA_BROWSER_HOMEOWNER_EMAIL, 'Requires isolated test credentials.');
    await page.goto('/login');
    await page.locator('#email').fill(process.env.HOA_BROWSER_HOMEOWNER_EMAIL);
    await page.locator('#password').fill(process.env.HOA_BROWSER_HOMEOWNER_PASSWORD);
    await page.locator('button[type="submit"]').click();
    await expect(page).toHaveURL(/\/homeowner\/?$/);
    await page.goto('/homeowner/my-profile');
    const phone = page.locator('[data-profile-field-wrapper="contact_number"] input');
    const photo = page.locator('[data-profile-field-wrapper="profile_photo_upload"]');
    await photo.locator('input[type="file"]').setInputFiles(path.resolve('public/images/hoa-hero.webp'));
    await expect(photo.locator('[data-filepond-item-state="processing-complete"]')).toHaveCount(1, { timeout: 60_000 });
    await phone.fill('0917-111-2222');
    await page.locator('#hoa-locale').selectOption('fil');
    await expect(page.locator('html')).toHaveAttribute('lang', 'fil', { timeout: 30_000 });
    await expect.poll(async () => (await phone.inputValue()).replace(/\D/g, '')).toBe('09171112222');
    await expect(photo.locator('[data-filepond-item-state="processing-complete"]')).toHaveCount(1);
    for (const width of [1280, 768, 390]) {
        await page.setViewportSize({ width, height: 900 });
        expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1)).toBeTruthy();
    }
    const results = await new AxeBuilder({ page }).withTags(['wcag2a', 'wcag2aa']).analyze();
    expect(results.violations.filter(issue => ['serious', 'critical'].includes(issue.impact))).toEqual([]);
    await page.locator('#hoa-locale').selectOption('en');
    await page.goto('/homeowner/my-household');
    await expect(page.getByText('PROVISIONAL RBI', { exact: false })).toBeVisible();
});
