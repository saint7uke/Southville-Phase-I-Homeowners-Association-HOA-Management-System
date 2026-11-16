import { expect as baseExpect, test } from '@playwright/test';
import { spawnSync } from 'node:child_process';

const expect = baseExpect.configure({ timeout: 30_000 });
test.describe.configure({ mode: 'serial' });
test.beforeEach(async ({ page }) => {
    test.skip(process.env.HOA_SMS_BROWSER_FIXTURES !== 'true', 'Requires the disposable SMS browser runner.');
    test.setTimeout(180_000);
    await page.route('**/*', route => {
        const url = new URL(route.request().url());
        return url.origin === new URL(process.env.PLAYWRIGHT_BASE_URL).origin ? route.continue() : route.abort();
    });
});
async function login(page, name = 'admin', panel = 'admin') {
    // Authenticate through the real endpoint, but direct this SMS fixture away from
    // dashboard lazy widgets that can starve the single-process Windows server.
    const destination = panel === 'admin' ? '/admin/sms-notifications' : panel === 'homeowner' ? '/homeowner/my-profile' : '/staff/service-requests';
    await page.route('**/login', async route => {
        if (route.request().method() !== 'POST') return route.fallback();
        const response = await route.fetch({ maxRedirects: 0 });
        expect(response.status()).toBe(302);
        await route.fulfill({ response, headers: { ...response.headers(), location: new URL(destination, process.env.PLAYWRIGHT_BASE_URL).href } });
    });
    await page.goto('/login', { waitUntil: 'domcontentloaded' });
    await page.getByLabel('Email address').fill(`${name}@alerts.example.test`);
    await page.locator('input[type="password"]').fill('AlertFixture!2026');
    await page.locator('button[type="submit"]').click();
    await expect(page).toHaveURL(new RegExp(`${destination}/?$`));
    await expect(page.locator('.swal2-popup')).toContainText('Login Successful!');
    await page.locator('.swal2-popup').getByRole('button', { name: 'OK', exact: true }).click();
}
function scalar(expression) {
    const result = spawnSync('php', ['-r', `require 'vendor/autoload.php'; $app=require 'bootstrap/app.php'; $app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap(); echo ${expression};`],
        { env: process.env, encoding: 'utf8', windowsHide: true });
    if (result.status !== 0) throw new Error('Disposable fixture lookup failed.');
    return result.stdout.trim();
}

test('application status cards use the HOA hero across states and screen sizes', async ({ page }) => {
    const titles = { Pending: 'Your application is under review', Active: 'Your application is approved', Rejected: 'Action is needed on your application' };
    for (const status of Object.keys(titles)) {
        const url = scalar(`Illuminate\\Support\\Facades\\URL::temporarySignedRoute('homeowner.register.submitted', now()->addHour(), ['user' => App\\Models\\User::where('email','${status.toLowerCase()}-status-fixture@example.test')->sole()->id], false)`);
        for (const width of [320, 375, 768, 1440]) {
            await page.setViewportSize({ width, height: 900 });
            await page.goto(url, { waitUntil: 'networkidle' });
            await expect(page.locator('#submitted-title')).toHaveText(titles[status]);
            await expect(page.locator('.hoa-submitted-logo')).toBeVisible();
            const refreshUrl = new URL(await page.getByRole('link', { name: 'Refresh status', exact: true }).getAttribute('href'), process.env.PLAYWRIGHT_BASE_URL);
            expect(refreshUrl.pathname).toBe(new URL(url, process.env.PLAYWRIGHT_BASE_URL).pathname);
            expect(Object.fromEntries(refreshUrl.searchParams)).toEqual(Object.fromEntries(new URL(url, process.env.PLAYWRIGHT_BASE_URL).searchParams));
            expect(await page.locator('.hoa-auth-overlay').evaluate(el => getComputedStyle(el).backgroundImage)).toContain('hoa-hero.webp');
            expect(await page.locator('.hoa-submitted-logo').evaluate(el => el.complete && el.naturalWidth > 0)).toBe(true);
            expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1)).toBe(true);
            expect(await page.locator('body').evaluate(el => getComputedStyle(el).margin)).toBe('0px');
            const eyebrow = await page.locator('.hoa-eyebrow').boundingBox();
            const heading = await page.locator('#submitted-title').boundingBox();
            expect(eyebrow.y + eyebrow.height).toBeLessThanOrEqual(heading.y);
            expect((await page.request.get('/images/hoa-hero.webp')).status()).toBe(200);
            await page.screenshot({ path: test.info().outputPath(`status-${status}-${width}.png`), fullPage: true });
        }
    }
});
test('test SMS cancels without sending, validates, and confirms one gateway acceptance', async ({ page }) => {
    await login(page);
    await page.goto('/admin/sms-notifications', { waitUntil: 'domcontentloaded' });
    await page.getByRole('button', { name: 'Send Test SMS', exact: true }).click();
    let dialog = page.locator('.fi-modal-window:visible');
    await dialog.getByLabel('Test mobile number').fill('09171234567');
    await dialog.getByRole('button', { name: 'Cancel', exact: true }).click();
    expect(scalar('App\\Models\\SmsDelivery::count()')).toBe('0');
    await page.getByRole('button', { name: 'Send Test SMS', exact: true }).click();
    dialog = page.locator('.fi-modal-window:visible');
    await dialog.getByLabel('Test mobile number').fill('bad');
    await expect(dialog.getByLabel('Test mobile number')).toHaveValue('');
    await dialog.getByRole('button', { name: 'Confirm test SMS', exact: true }).click();
    const modal = page.locator('.swal2-popup');
    await expect(modal).toHaveCount(0);
    expect(await dialog.getByLabel('Test mobile number').evaluate(input => input.validity.valid)).toBe(false);
    expect(scalar('App\\Models\\SmsDelivery::count()')).toBe('0');
    await dialog.getByLabel('Test mobile number').fill('09171234567');
    await dialog.getByRole('button', { name: 'Confirm test SMS', exact: true }).click();
    await expect(modal).toContainText('SMS Submitted to Gateway Successfully!');
    await expect(modal).toContainText('does not confirm handset delivery');
    expect(scalar('App\\Models\\SmsDelivery::where("type","test")->count()')).toBe('1');
    await modal.getByRole('button', { name: 'OK', exact: true }).click();
    await page.reload();
    await expect(modal).toHaveCount(0);
});
test('Important announcement requires confirmation and queues once', async ({ page }) => {
    await login(page);
    await page.goto('/admin/announcements', { waitUntil: 'domcontentloaded' });
    await page.getByRole('button', { name: 'Send Important SMS', exact: true }).click();
    const dialog = page.locator('.fi-modal-window:visible');
    await expect(dialog).toContainText('Eligible unique phone numbers');
    await dialog.getByRole('button', { name: 'Cancel', exact: true }).click();
    expect(scalar('Illuminate\\Support\\Facades\\DB::table("announcement_sms_blasts")->count()')).toBe('0');
    await page.getByRole('button', { name: 'Send Important SMS', exact: true }).click();
    await dialog.getByRole('button', { name: 'Confirm', exact: true }).click();
    const modal = page.locator('.swal2-popup');
    await expect(modal).toContainText('Important Announcement SMS Queued Successfully!');
    expect(scalar('Illuminate\\Support\\Facades\\DB::table("announcement_sms_blasts")->count()')).toBe('1');
    await modal.getByRole('button', { name: 'OK', exact: true }).click();
    await page.reload();
    await expect(page.getByRole('button', { name: 'Send Important SMS', exact: true })).toHaveCount(0);
});
test('homeowner verifies current number, saves consent once and can opt out', async ({ page }) => {
    await login(page, 'resident', 'homeowner');
    await page.goto('/homeowner/my-profile', { waitUntil: 'domcontentloaded' });
    const enrollment = page.locator('[data-sms-enrollment]');
    await expect(enrollment).toBeVisible();
    await enrollment.locator('[data-sms-request]').click();
    const modal = page.locator('.swal2-popup');
    await expect(modal).toContainText('Verification SMS Queued Successfully!');
    await modal.getByRole('button', { name: 'OK', exact: true }).click();
    const code = scalar('Illuminate\\Support\\Facades\\DB::table("sms_sync_states")->where("key","synthetic-browser-otp")->value("value")');
    expect(code).toMatch(/^\d{6}$/);
    await enrollment.locator('[data-sms-code]').fill(code);
    await enrollment.locator('[data-sms-confirm]').click();
    await expect(modal).toContainText('Mobile Number Verified Successfully!');
    await modal.getByRole('button', { name: 'OK', exact: true }).click();
    await enrollment.locator('[data-sms-save]').click();
    await expect(modal).toContainText('SMS Preferences Updated Successfully!');
    await modal.getByRole('button', { name: 'OK', exact: true }).click();
    await expect(enrollment.locator('[name="sms_verification_id"]')).toHaveValue('');
    await enrollment.locator('[name="sms_transactional_opt_in"]').uncheck();
    await enrollment.locator('[name="sms_announcement_opt_in"]').uncheck();
    await enrollment.locator('[data-sms-save]').click();
    await expect(modal).toContainText('SMS Preferences Updated Successfully!');
    await modal.getByRole('button', { name: 'OK', exact: true }).click();
    await page.reload();
    await expect(enrollment.locator('[name="sms_transactional_opt_in"]')).not.toBeChecked();
    await expect(enrollment.locator('[name="sms_announcement_opt_in"]')).not.toBeChecked();
});
test('staff has no gateway management access; guest SMS remains optional on mobile', async ({ page }) => {
    await login(page, 'staff', 'staff');
    expect((await page.request.get('/admin/sms-notifications')).status()).toBe(403);
    await page.context().clearCookies();
    await page.setViewportSize({ width: 375, height: 812 });
    await page.goto('/homeowner/register', { waitUntil: 'domcontentloaded' });
    await page.locator('[data-role-card="Owner"]').click();
    const enrollment = page.locator('[data-sms-enrollment]');
    await expect(enrollment).toContainText('Registration and account access do not require SMS enrollment.');
    await expect(enrollment.locator('[name="sms_transactional_opt_in"]')).not.toBeChecked();
    await expect(enrollment.locator('[name="sms_announcement_opt_in"]')).not.toBeChecked();
    expect(await page.evaluate(() => document.documentElement.scrollWidth > innerWidth + 1)).toBe(false);
});

test('registration phone and OTP inputs are distinct, numeric and responsive', async ({ page }) => {
    await page.setViewportSize({ width: 375, height: 812 });
    await page.goto('/homeowner/register', { waitUntil: 'domcontentloaded' });
    await page.locator('[data-role-card="Owner"]').click();
    const phone = page.locator('#contact_number');
    const enrollment = page.locator('[data-sms-enrollment]');
    const code = enrollment.locator('[data-sms-code]');
    const send = enrollment.locator('[data-sms-request]');
    const verify = enrollment.locator('[data-sms-confirm]');
    await expect(send).toBeDisabled();
    await phone.pressSequentially('09ab+ -17');
    await expect(phone).toHaveValue('0917');
    await phone.fill('09171234567');
    await expect(enrollment.locator('[data-sms-destination]')).toHaveText('09171234567');
    await expect(send).toBeEnabled();
    await expect(verify).toBeDisabled();
    await enrollment.locator('[data-sms-edit]').click();
    await expect(phone).toBeFocused();
    await phone.evaluate(input => {
        const transfer = new DataTransfer();
        transfer.setData('text', '+639171234567');
        input.dispatchEvent(new ClipboardEvent('paste', { clipboardData: transfer, bubbles: true, cancelable: true }));
    });
    await expect(phone).toHaveValue('09171234567');
    await phone.fill('09171234567');
    await code.pressSequentially('0a1-234567');
    await expect(code).toHaveValue('012345');
    await expect(verify).toBeDisabled();
    await code.fill('123');
    expect(await code.evaluate(input => input.validity.valid)).toBe(true);
    await expect(verify).toBeDisabled();
    for (const width of [375, 1280]) {
        await page.setViewportSize({ width, height: 900 });
        await enrollment.scrollIntoViewIfNeeded();
        expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1)).toBe(true);
        const styles = await enrollment.evaluate(element => ({
            display: getComputedStyle(element).display,
            label: getComputedStyle(element.querySelector('.hoa-sms-consent')).display,
            color: getComputedStyle(element.querySelector('button')).color,
            height: element.querySelector('[data-sms-code]').getBoundingClientRect().height,
        }));
        expect(styles.display).toBe('grid');
        expect(styles.label).toBe('flex');
        expect(styles.color).toBe('rgb(255, 255, 255)');
        expect(styles.height).toBeGreaterThanOrEqual(44);
        await enrollment.screenshot({ path: test.info().outputPath(`sms-enrollment-${width}.png`) });
    }
});

test('registration ignores stale responses and rejects malformed success without duplicate submissions', async ({ page }) => {
    await page.goto('/homeowner/register', { waitUntil: 'domcontentloaded' });
    await page.locator('[data-role-card="Owner"]').click();
    const phone = page.locator('#contact_number');
    const enrollment = page.locator('[data-sms-enrollment]');
    const send = enrollment.locator('[data-sms-request]');
    const verify = enrollment.locator('[data-sms-confirm]');
    const proof = enrollment.locator('[name="sms_verification_id"]');
    let requests = 0;
    let mode = 'stale';
    await page.route('**/sms/verification/request', async route => {
        requests++;
        if (mode === 'network') return route.abort('failed');
        if (mode === 'stale') await new Promise(resolve => setTimeout(resolve, 1000));
        await route.fulfill({ status: mode === 'malformed' ? 200 : 202, contentType: 'application/json', body: JSON.stringify(mode === 'malformed' ? { message: 'Unexpected success' } : {
            verification_id: '12345678-1234-1234-1234-123456789abc', message: 'Verification SMS Queued Successfully!', resend_after: 60,
        }) });
    });
    await phone.fill('09171234567');
    await send.click();
    await expect(send).toBeDisabled();
    await phone.fill('09181234567');
    await expect(send).toBeEnabled();
    await expect(proof).toHaveValue('');
    await expect(page.locator('.swal2-popup')).toHaveCount(0);
    mode = 'malformed';
    await send.click();
    await expect(enrollment.locator('[data-sms-result]')).toContainText('could not be completed');
    await expect(page.locator('.swal2-popup')).toHaveCount(0);
    mode = 'network';
    await send.click();
    await expect(enrollment.locator('[data-sms-result]')).toContainText('could not be completed');
    await expect(send).toBeEnabled();
    await expect(page.locator('.swal2-popup')).toHaveCount(0);
    mode = 'valid';
    await send.click();
    const modal = page.locator('.swal2-popup');
    await expect(modal).toContainText('Verification SMS Queued Successfully!');
    await modal.getByRole('button', { name: 'OK', exact: true }).click();
    await expect(send).toBeDisabled();
    await expect(enrollment.locator('[data-sms-cooldown]')).toContainText('seconds');
    await enrollment.locator('[data-sms-code]').fill('012345');
    let confirmations = 0;
    await page.route('**/sms/verification/confirm', async route => {
        confirmations++;
        if (confirmations === 1) return route.fulfill({ status: 422, contentType: 'application/json', body: JSON.stringify({ errors: { code: ['The SMS verification code is invalid, expired, or already used.'] } }) });
        await route.fulfill({ contentType: 'application/json', body: JSON.stringify({ verified: true, message: 'Mobile Number Verified Successfully!' }) });
    });
    await verify.click();
    await expect(enrollment.locator('[data-sms-result]')).toContainText('invalid, expired');
    await expect(enrollment.locator('[data-sms-code]')).toBeFocused();
    await expect(modal).toHaveCount(0);
    await verify.click();
    await expect(modal).toContainText('Mobile Number Verified Successfully!');
    await modal.getByRole('button', { name: 'OK', exact: true }).click();
    await expect(verify).toBeDisabled();
    expect(requests).toBe(4);
    expect(confirmations).toBe(2);
    await phone.fill('09191234567');
    await expect(proof).toHaveValue('');
});
