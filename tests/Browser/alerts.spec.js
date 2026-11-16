import { expect as baseExpect, test } from '@playwright/test';

const expect = baseExpect.configure({ timeout: 30_000 });

test.beforeEach(async ({ page }) => {
    test.skip(process.env.HOA_ALERT_BROWSER_FIXTURES !== 'true', 'Requires the isolated alert browser runner.');
    test.setTimeout(90_000);
    await page.goto('/login');
    await page.getByLabel('Email address').fill('admin@alerts.example.test');
    await page.locator('input[type="password"]').fill('AlertFixture!2026');
    await page.locator('button[type="submit"]').click();
    await expect(page).toHaveURL(/\/admin\/?$/, { timeout: 30_000 });
    await expect(page.locator('html')).toHaveAttribute('data-hoa-alerts-ready', 'true');
    await expect(page.locator('.swal2-popup')).toContainText('Login Successful!');
    await page.locator('.swal2-popup').getByRole('button', { name: 'OK', exact: true }).click();
});

test('actual mounted archive action cancels without mutation and confirms only once', async ({ page }) => {
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));
    await page.goto('/admin/projects');
    const row = page.getByRole('row').filter({ hasText: 'Alert browser project' });
    await row.getByRole('button', { name: 'Archive', exact: true }).click();
    const modal = page.locator('.swal2-popup');
    await expect(modal).toBeVisible();
    await modal.getByRole('button', { name: 'Cancel', exact: true }).click();
    await expect(row).toBeVisible();
    let executions = 0;
    page.on('request', request => { if (request.postData()?.includes('callMountedAction')) executions++; });
    await row.getByRole('button', { name: 'Archive', exact: true }).click();
    await expect(modal).toBeVisible();
    await modal.getByRole('button', { name: 'Confirm', exact: true }).click({ clickCount: 2 });
    await expect(modal).toContainText('Project Archived Successfully!');
    expect(executions).toBe(1);
    await modal.getByRole('button', { name: 'OK', exact: true }).click();
    await expect(row).toHaveCount(0);
    expect(errors).toEqual([]);
});

test('restore has a clickable confirmation and one backend result modal', async ({ page }) => {
    await page.goto('/admin/contact-messages/1/edit');
    await page.getByRole('button', { name: 'Record actions', exact: true }).click();
    await page.getByRole('button', { name: 'Restore', exact: true }).click();
    const modal = page.locator('.swal2-popup');
    await expect(modal).toBeVisible();
    await modal.getByRole('button', { name: 'Yes, Restore', exact: true }).click();
    await expect(modal).toContainText('Contact Inquiry Restored Successfully!');
    await modal.getByRole('button', { name: 'OK', exact: true }).click();
    await page.reload();
    await expect(page.locator('.swal2-popup')).toHaveCount(0);
});

test('input dialogs remain Filament and server validation preserves the form', async ({ page }) => {
    await page.goto('/admin/projects');
    await page.getByRole('button', { name: 'Add project', exact: true }).click();
    const dialog = page.locator('.fi-modal-window:visible');
    await expect(dialog).toBeVisible();
    await expect(dialog.getByLabel('Title', { exact: false })).toBeVisible();
    await dialog.getByLabel('Title', { exact: false }).fill('Retained validation value');
    await expect(page.locator('.swal2-popup')).toHaveCount(0);
    await dialog.evaluate(node => { (node.closest('form') || node.querySelector('form')).noValidate = true; });
    await dialog.getByRole('button', { name: 'Submit', exact: true }).click();
    const validation = page.locator('.swal2-popup');
    await expect(validation).toContainText('Please review the form');
    await validation.getByRole('button', { name: 'OK', exact: true }).click();
    await expect(dialog).toBeVisible();
    await expect(dialog.getByLabel('Title', { exact: false })).toHaveValue('Retained validation value');
    await expect(page.locator('.swal2-success')).toHaveCount(0);
});

test('form-submit confirmation handles Enter and saves once after confirmation', async ({ page }) => {
    await page.goto('/admin/dues-settings/create');
    await page.getByLabel('Name', { exact: false }).fill('Browser confirmation schedule');
    await page.getByLabel('Amount', { exact: false }).fill('100');
    // Set the date-picker's Livewire state; this test targets submit routing, not calendar navigation.
    await page.evaluate(() => {
        const component = document.querySelector('.fi-resource-create-record-page');
        window.Livewire.find(component.getAttribute('wire:id')).$set('data.starts_on', '2026-11-01', false);
    });
    await page.getByLabel('Name', { exact: false }).press('Enter');
    const modal = page.locator('.swal2-popup');
    await expect(modal).toContainText('Create dues schedule?');
    await modal.getByRole('button', { name: 'Cancel', exact: true }).click();
    await expect(page).toHaveURL(/dues-settings\/create$/);
    let saves = 0;
    page.on('request', request => { if (request.postData()?.includes('"method":"create"')) saves++; });
    await page.getByRole('button', { name: 'Create', exact: true }).click();
    await expect(modal).toContainText('Create dues schedule?');
    await modal.getByRole('button', { name: 'Confirm', exact: true }).click();
    await expect(modal).toContainText('Dues Settings Created Successfully!');
    expect(saves).toBe(1);
});

test('shared feedback loads in staff and homeowner panels and respects access checks', async ({ page }) => {
    test.setTimeout(180_000);
    for (const [name, panel] of [['staff', 'staff'], ['resident', 'homeowner']]) {
        await page.context().clearCookies();
        await page.goto('/login');
        await page.getByLabel('Email address').fill(`${name}@alerts.example.test`);
        await page.locator('input[type="password"]').fill('AlertFixture!2026');
        await page.locator('button[type="submit"]').click();
        await expect(page).toHaveURL(new RegExp(`/${panel}/?$`), { timeout: 30_000 });
        await expect(page.locator('html')).toHaveAttribute('data-hoa-alerts-ready', 'true');
        await page.locator('.swal2-popup').getByRole('button', { name: 'OK', exact: true }).click();
        await page.evaluate(() => { document.documentElement.lang = 'fil'; void window.HoaAlerts.notify({ id: `panel-${location.pathname}`, level: 'success', title: 'Homeowner Created Successfully!' }); });
        const modal = page.locator('.swal2-popup');
        await expect(modal).toContainText('Nalikha nang matagumpay ang homeowner!');
        await modal.getByRole('button', { name: 'OK', exact: true }).click();
        const forbidden = await page.request.get('/admin/roles');
        expect(forbidden.status()).toBe(403);
    }
});

test('feedback safely renders text, deduplicates IDs and fits a mobile dark layout', async ({ page }) => {
    await page.setViewportSize({ width: 320, height: 700 });
    await page.evaluate(() => {
        document.documentElement.classList.add('dark');
        window.HoaAlerts.notify({ id: 'escaped-fixture', level: 'success', title: '<img src=x onerror="window.evil=true">', message: 'Saved.' });
        window.HoaAlerts.notify({ id: 'escaped-fixture', level: 'success', title: 'Duplicate must not appear', message: 'Saved.' });
    });
    const modal = page.locator('.swal2-popup');
    await expect(modal).toContainText('<img src=x');
    await expect(modal.locator('.swal2-title img')).toHaveCount(0);
    expect(await page.evaluate(() => window.evil)).toBeUndefined();
    expect(await page.evaluate(() => document.documentElement.scrollWidth > innerWidth + 1)).toBe(false);
    await modal.getByRole('button', { name: 'OK' }).click();
    await expect(modal).toHaveCount(0);
    await page.evaluate(() => window.HoaAlerts.notify({ id: 'escaped-fixture', level: 'success', title: 'Do not replay' }));
    await expect(modal).toHaveCount(0);
    expect(await page.evaluate(() => window.HoaAlerts.notify({ id: 'missing-title', level: 'success' }))).toBe(false);
    await expect(modal).toHaveCount(0);
});

test('HTTP and network failures never present success or server exception text', async ({ page }) => {
    for (const status of [401, 403, 404, 419, 422, 429, 500, 0]) {
        await page.evaluate(status => { void window.HoaAlerts.handleRequestError({ status, response: { status, data: { message: 'private exception' } } }); }, status);
        const modal = page.locator('.swal2-popup');
        await expect(modal).toBeVisible();
        await expect(modal.locator('.swal2-error')).toBeVisible();
        await expect(modal).not.toContainText('private exception');
        await modal.getByRole('button', { name: 'OK' }).click();
    }
});

test('a failed Livewire request produces one session-expiry modal and no success', async ({ page }) => {
    await page.goto('/admin/projects');
    let requests = 0;
    await page.route('**/livewire/update', route => {
        requests++;
        return route.fulfill({ status: 419, contentType: 'text/html', body: 'Private server details must not appear.' });
    });
    await page.getByRole('button', { name: 'Add project', exact: true }).click();
    const modal = page.locator('.swal2-popup');
    await expect(modal).toContainText('Your session has expired.');
    await expect(modal).not.toContainText('Private server');
    await expect(page.locator('iframe')).toHaveCount(0);
    await expect(page.locator('.swal2-success')).toHaveCount(0);
    expect(requests).toBe(1);
    await modal.getByRole('button', { name: 'OK', exact: true }).click();
    await expect(modal).toHaveCount(0);
});

test('an interrupted connection produces safe uncertain-outcome feedback without retrying', async ({ page }) => {
    await page.goto('/admin/projects');
    let requests = 0;
    await page.route('**/livewire/update', route => { requests++; return route.abort('failed'); });
    await page.getByRole('button', { name: 'Add project', exact: true }).click();
    const modal = page.locator('.swal2-popup');
    await expect(modal).toContainText('The connection was interrupted.');
    await expect(modal).toContainText('Check whether the action completed');
    await expect(page.locator('iframe')).toHaveCount(0);
    expect(requests).toBe(1);
    await modal.getByRole('button', { name: 'OK', exact: true }).click();
    await expect(modal).toHaveCount(0);
});

test('account access changes confirm while ordinary edits do not', async ({ page }) => {
    await page.goto('/admin/users/1/edit');
    await page.getByLabel('Contact number', { exact: false }).fill('09171234567');
    await page.getByRole('button', { name: 'Save changes', exact: true }).click();
    const modal = page.locator('.swal2-popup');
    await expect(modal).toContainText('User Account Updated Successfully!');
    await expect(modal).not.toContainText('Confirm account access changes');
    await modal.getByRole('button', { name: 'OK', exact: true }).click();
    await page.getByLabel('Role', { exact: false }).selectOption('hoa_staff');
    await page.getByRole('button', { name: 'Save changes', exact: true }).click();
    await expect(modal).toContainText('Confirm account access changes');
    await modal.getByRole('button', { name: 'Cancel', exact: true }).click();
    await page.reload();
    await expect(page.getByLabel('Role', { exact: false })).toHaveValue('hoa_admin');
});
