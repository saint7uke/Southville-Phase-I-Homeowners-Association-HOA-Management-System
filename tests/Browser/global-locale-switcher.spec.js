import AxeBuilder from '@axe-core/playwright';
import { expect, test } from '@playwright/test';
import path from 'node:path';

test.beforeEach(async ({ baseURL }) => {
    test.skip(process.env.HOA_BROWSER_GLOBAL_LOCALE_FIXTURE !== 'true', 'Requires the guarded GlobalLocaleBrowserSeeder on isolated SQLite storage.');
    test.setTimeout(180_000);
    expect(new URL(baseURL).hostname).toMatch(/^(127\.0\.0\.1|localhost)$/);
});

async function ready(page) {
    await expect(page.locator('#hoa-locale')).toHaveCount(1);
    await expect(page.locator('#hoa-locale')).toBeVisible();
    await expect(page.locator('html')).toHaveAttribute('data-locale-ready', 'true');
}

async function switchTo(page, locale) {
    await page.locator('#hoa-locale').selectOption(locale);
    await expect(page.locator('html')).toHaveAttribute('lang', locale, { timeout: 30_000 });
    await expect(page.locator('#hoa-locale')).toBeEnabled();
}

async function login(page, role, panel) {
    await page.goto('/login');
    await page.locator('#email').fill(`${role}@locale.example.test`);
    await page.locator('#password').fill('BrowserTest!2026');
    await page.locator('button[type="submit"]').click();
    await expect(page).toHaveURL(new RegExp(`/${panel}/?$`), { timeout: 60_000 });
    await ready(page);
    if (await page.locator('#hoa-locale').inputValue() !== 'en') await switchTo(page, 'en');
}

async function compact(page) {
    await ready(page);
    const control = page.getByRole('combobox', { name: /^(Language|Wika)$/ });
    expect(await control.evaluate(node => getComputedStyle(node).fontSize)).toBe('14px');
    expect(await control.evaluate(node => getComputedStyle(node).minBlockSize)).toBe('44px');
    expect(Math.round((await control.boundingBox()).height * 100) / 100).toBeGreaterThanOrEqual(44);
    expect(await page.evaluate(() => document.documentElement.scrollWidth > innerWidth + 1)).toBe(false);
    expect(await page.locator('label[for="hoa-locale"]').evaluate(node => getComputedStyle(node).position)).toBe('absolute');
}

test('public navbar keeps one compact switcher visible with the mobile menu closed and after scrolling', async ({ page }) => {
    await page.goto('/');
    await ready(page);
    await expect(page.locator('.hoa-nav-utilities #hoa-locale')).toBeVisible();
    for (const width of [320, 768, 1440]) {
        await page.setViewportSize({ width, height: 900 });
        await compact(page);
    }
    await page.setViewportSize({ width: 320, height: 900 });
    await expect(page.getByRole('button', { name: 'Open navigation menu' })).toHaveAttribute('aria-expanded', 'false');
    await page.getByRole('button', { name: 'Open navigation menu' }).click();
    await switchTo(page, 'fil');
    await expect(page.getByRole('button', { name: 'Close navigation menu' })).toHaveAttribute('aria-expanded', 'true');
    await page.getByRole('button', { name: 'Close navigation menu' }).press('Escape');
    await page.evaluate(() => scrollTo(0, 500));
    await expect(page.locator('[data-nav]')).toHaveClass(/is-scrolled/);
    await compact(page);
    await switchTo(page, 'en');
    await page.locator('#hoa-locale').focus();
    await expect(page.locator('#hoa-locale')).toBeFocused();
    const axe = await new AxeBuilder({ page }).include('[data-nav]').withTags(['wcag2a', 'wcag2aa']).analyze();
    expect(axe.violations.filter(issue => ['serious', 'critical'].includes(issue.impact))).toEqual([]);
});

test('auth and recovery screens share the control and preserve unsaved credentials', async ({ page }) => {
    await page.goto('/login');
    await page.locator('#email').fill('unsaved@example.test');
    await page.locator('#password').fill('UnsavedPassword!2026');
    await switchTo(page, 'fil');
    await expect(page.locator('#email')).toHaveValue('unsaved@example.test');
    await expect(page.locator('#password')).toHaveValue('UnsavedPassword!2026');
    await page.reload();
    await expect(page.locator('html')).toHaveAttribute('lang', 'fil');
    for (const url of ['/login', '/homeowner/register', '/homeowner/forgot-password', '/homeowner/reset-password/synthetic-token?email=unsaved%40example.test', '/admin/password-reset/request']) {
        await page.goto(url);
        for (const width of [320, 768, 1440]) {
            await page.setViewportSize({ width, height: 900 });
            await compact(page);
        }
    }
    await page.goto('/login');
    const axe = await new AxeBuilder({ page }).withTags(['wcag2a', 'wcag2aa']).analyze();
    expect(axe.violations.filter(issue => ['serious', 'critical'].includes(issue.impact))).toEqual([]);
});

test('pending and failed changes retain their dimensions and send one request per selection', async ({ page }) => {
    await page.goto('/login');
    await ready(page);
    let release;
    let pending;
    let requests = 0;
    const requestSeen = new Promise(resolve => { pending = resolve; });
    const allowResponse = new Promise(resolve => { release = resolve; });
    await page.route('**/locale', async route => {
        requests++;
        pending();
        await allowResponse;
        await route.fulfill({ status: 503, contentType: 'application/json', body: '{}' });
    });
    const before = await page.locator('#hoa-locale').boundingBox();
    await page.locator('#hoa-locale').selectOption('fil');
    await requestSeen;
    await expect(page.locator('#hoa-locale')).toBeDisabled();
    await expect(page.locator('[data-locale-switcher]')).toHaveAttribute('aria-busy', 'true');
    await expect(page.locator('.hoa-locale-spinner')).toBeVisible();
    await page.evaluate(() => document.body.appendChild(document.createElement('span')));
    await expect(page.locator('#hoa-locale')).toHaveValue('fil');
    await page.locator('#hoa-locale').dispatchEvent('change');
    const during = await page.locator('#hoa-locale').boundingBox();
    expect([during.width, during.height]).toEqual([before.width, before.height]);
    release();
    await expect(page.locator('#hoa-locale')).toBeEnabled();
    await expect(page.locator('#hoa-locale')).toHaveValue('en');
    await expect(page.locator('#hoa-locale-status')).toContainText('Language could not be changed');
    expect(requests).toBe(1);
    await page.unroute('**/locale');
    requests = 0;
    page.on('request', request => { if (new URL(request.url()).pathname === '/locale') requests++; });
    await page.evaluate(() => { for (let i = 0; i < 3; i++) document.dispatchEvent(new Event('livewire:navigated')); });
    await switchTo(page, 'fil');
    expect(requests).toBe(1);
    await switchTo(page, 'en');
    expect(requests).toBe(2);
});

for (const [role, panel] of [['admin', 'admin'], ['staff', 'staff'], ['resident', 'homeowner']]) {
    test(`${panel} header aligns the switcher before the avatar in both themes`, async ({ page }) => {
        await login(page, role, panel);
        for (const width of [320, 768, 1440]) {
            await page.setViewportSize({ width, height: 900 });
            await compact(page);
        }
        const control = await page.locator('#hoa-locale').boundingBox();
        const avatar = await page.locator('.fi-user-menu-trigger').first().boundingBox();
        expect(control.x + control.width).toBeLessThanOrEqual(avatar.x);
        expect(Math.abs(control.y + control.height / 2 - avatar.y - avatar.height / 2)).toBeLessThanOrEqual(4);
        for (const theme of ['dark', 'light']) {
            await page.evaluate(value => window.dispatchEvent(new CustomEvent('theme-changed', { detail: value })), theme);
            if (theme === 'dark') await expect(page.locator('html')).toHaveClass(/dark/);
            else await expect(page.locator('html')).not.toHaveClass(/dark/);
            const axe = await new AxeBuilder({ page }).include('[data-locale-switcher]').withTags(['wcag2a', 'wcag2aa']).analyze();
            expect(axe.violations.filter(issue => ['serious', 'critical'].includes(issue.impact))).toEqual([]);
        }
        await switchTo(page, 'fil');
        await page.reload();
        await expect(page.locator('html')).toHaveAttribute('lang', 'fil');
        await compact(page);
    });
}

test('profile language changes preserve unsaved input, upload and validation', async ({ page }) => {
    await login(page, 'resident', 'homeowner');
    await page.goto('/homeowner/my-profile');
    const phone = page.locator('[data-profile-field-wrapper="contact_number"] input');
    const photo = page.locator('[data-profile-field-wrapper="profile_photo_upload"]');
    await photo.locator('input[type="file"]').setInputFiles(path.resolve('public/images/hoa-hero.webp'));
    await expect(photo.locator('[data-filepond-item-state="processing-complete"]')).toHaveCount(1, { timeout: 60_000 });
    await phone.fill('0917');
    await expect(page.locator('[data-profile-field-wrapper="contact_number"] [role="status"]')).toContainText('Enter an 11-digit');
    await switchTo(page, 'fil');
    await expect(phone).toHaveValue('0917');
    await expect(photo.locator('[data-filepond-item-state="processing-complete"]')).toHaveCount(1);
    await expect(page.locator('[data-profile-field-wrapper="contact_number"] [role="status"]')).toContainText('Maglagay ng 11-digit');
});

test('changing language preserves an open project modal and its unsaved title', async ({ page }) => {
    await login(page, 'admin', 'admin');
    await page.goto('/admin/projects');
    await page.getByRole('button', { name: 'Add project', exact: true }).click();
    const dialog = page.getByRole('dialog', { name: 'Add project', exact: true });
    const title = dialog.locator('input[type="text"]').first();
    await title.fill('Unchanged resident content');
    // The modal correctly traps focus; exercise state retention without dismantling that trap.
    await page.locator('#hoa-locale').selectOption('fil', { force: true });
    await expect(page.locator('html')).toHaveAttribute('lang', 'fil');
    await expect(dialog.locator('.fi-modal-window')).toBeVisible();
    await expect(title).toHaveValue('Unchanged resident content');
});
