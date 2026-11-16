import { test, expect as baseExpect } from '@playwright/test';
const expect = baseExpect.configure({ timeout: 30_000 });

test.describe.configure({ mode: 'serial' });
test.beforeEach(async ({ page }, testInfo) => {
    test.skip(process.env.HOA_HEAD_BROWSER_FIXTURES !== 'true', 'Requires the isolated household head runner.');
    test.setTimeout(150_000);
    const panel = testInfo.title.startsWith('staff ') ? 'staff' : 'admin';
    await page.route('**/*', route => new URL(route.request().url()).origin === new URL(process.env.PLAYWRIGHT_BASE_URL).origin ? route.continue() : route.abort());
    await page.route('**/login', async route => {
        if (route.request().method() !== 'POST') return route.fallback();
        const response = await route.fetch({ maxRedirects: 0 });
        await route.fulfill({ response, headers: { ...response.headers(), location: new URL(`/${panel}/households`, process.env.PLAYWRIGHT_BASE_URL).href } });
    });
    await page.goto('/login', { waitUntil: 'domcontentloaded' });
    await page.getByLabel('Email address').fill(`${panel}@alerts.example.test`);
    await page.locator('input[type="password"]').fill('AlertFixture!2026');
    await page.locator('button[type="submit"]').click();
    await expect(page).toHaveURL(new RegExp(`/${panel}/households/?$`));
    await expect(page.locator('.swal2-popup')).toContainText('Login Successful!');
    await page.locator('.swal2-popup').getByRole('button', { name: 'OK', exact: true }).click();
    await expect(page.locator('.swal2-popup')).toHaveCount(0);
});

for (const panel of ['admin', 'staff']) {
    test(`${panel} household dialogs omit house number and ordinary saves complete`, async ({ page }) => {
        await page.goto(`/${panel}/households`, { waitUntil: 'domcontentloaded' });
        await page.getByRole('button', { name: 'Add household', exact: true }).click();
        let dialog = page.getByRole('dialog', { name: 'Add household', exact: true });
        await expect(dialog.getByLabel('House number', { exact: true })).toHaveCount(0);
        await expect(dialog.getByRole('textbox', { name: /^Block/ })).toBeVisible();
        await dialog.getByRole('button', { name: 'Cancel', exact: true }).click();
        await page.getByRole('button', { name: 'Edit household', exact: true }).click();
        dialog = page.getByRole('dialog', { name: 'Edit household', exact: true });
        await expect(dialog.getByLabel('House number', { exact: true })).toHaveCount(0);
        await dialog.getByLabel('Occupancy', { exact: true }).fill(`Reviewed by ${panel}`);
        await dialog.getByRole('button', { name: 'Submit', exact: true }).click();
        await expect(page.locator('.swal2-popup')).toContainText('Household Information Updated Successfully!');
        await page.locator('.swal2-popup').getByRole('button', { name: 'OK', exact: true }).click();
        await page.reload({ waitUntil: 'domcontentloaded' });
        await page.getByRole('button', { name: 'Edit household', exact: true }).click();
        dialog = page.getByRole('dialog', { name: 'Edit household', exact: true });
        await expect(dialog.getByLabel('Occupancy', { exact: true })).toHaveValue(`Reviewed by ${panel}`);
    });
}

async function reviewAll(dialog) {
    const relationships = dialog.getByRole('combobox', { name: /^Relationship for / });
    await expect(relationships).toHaveCount(3);
    for (const field of await relationships.all()) {
        if (!(await field.isDisabled())) await field.selectOption('Other');
    }
    await dialog.getByRole('checkbox', { name: /I reviewed every current member/ }).check();
}

async function success(page) {
    await expect(page.locator('.swal2-popup')).toContainText('Household Head and Relationships Confirmed Successfully!');
    await expect(page.locator('.swal2-popup')).toHaveCount(1);
    await page.locator('.swal2-popup').getByRole('button', { name: 'OK', exact: true }).click();
    await expect(page.locator('.swal2-popup')).toHaveCount(0);
}

test('members entry point cancels safely, then assigns once through the actual dialog', async ({ page }) => {
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));
    await page.goto('/admin/household-members?household=1', { waitUntil: 'domcontentloaded' });
    const row = page.getByRole('row').filter({ hasText: 'Head Browser Candidate' });
    await row.getByRole('button', { name: 'Assign head', exact: true }).click();
    let dialog = page.locator('.fi-modal-window:visible');
    await expect(dialog.getByRole('combobox', { name: /^Household head/ })).toHaveValue('2');
    await dialog.getByRole('button', { name: 'Cancel', exact: true }).click();
    await expect(page.getByText('No household head assigned. Relationships require HOA review.', { exact: true })).toBeVisible();
    await expect(page.locator('.swal2-success')).toHaveCount(0);
    await row.getByRole('button', { name: 'Assign head', exact: true }).click();
    dialog = page.locator('.fi-modal-window:visible');
    await reviewAll(dialog);
    let executions = 0;
    page.on('request', request => { if (request.postData()?.includes('callMountedAction')) executions++; });
    await dialog.getByRole('button', { name: 'Submit', exact: true }).click();
    await success(page);
    expect(executions).toBe(1);
    await page.reload({ waitUntil: 'domcontentloaded' });
    await expect(row).toContainText('Head');
    await expect(page.getByText('No household head assigned. Relationships require HOA review.', { exact: true })).toHaveCount(0);
    expect(errors).toEqual([]);
});

test('household selector requires all relationships and replaces the head consistently', async ({ page }) => {
    await page.goto('/admin/households', { waitUntil: 'domcontentloaded' });
    await page.getByRole('button', { name: 'Edit household', exact: true }).click();
    const dialog = page.locator('.fi-modal-window:visible');
    await expect(dialog.getByLabel('House number', { exact: true })).toHaveCount(0);
    await expect(dialog.locator('input[name="house_number"], input[id$=".house_number"]')).toHaveCount(0);
    const selector = dialog.getByRole('combobox', { name: /^Household head/ });
    await expect(selector).toBeDisabled();
    await dialog.getByRole('switch', { name: 'Review household head and all relationships', exact: true }).click();
    await expect(selector).toBeEnabled();
    await selector.selectOption('3');
    await expect(dialog.getByRole('combobox', { name: /^Relationship for Head Browser Candidate/ })).toHaveValue('');
    await reviewAll(dialog);
    await dialog.getByRole('button', { name: 'Submit', exact: true }).click();
    await success(page);
    await expect(page.getByRole('row').filter({ hasText: 'Edit household' })).toContainText('Head Browser Second');
    await page.goto('/admin/household-members?household=1', { waitUntil: 'domcontentloaded' });
    await expect(page.getByRole('row').filter({ hasText: 'Head Browser Candidate' })).toContainText('Other');
    await expect(page.getByRole('row').filter({ hasText: 'Head Browser Second' })).toContainText('Head');
});

test('failed review shows no success and clear confirmation supports cancellation and completion', async ({ page }) => {
    await page.goto('/admin/household-members?household=1', { waitUntil: 'domcontentloaded' });
    const row = page.getByRole('row').filter({ hasText: 'Head Browser Candidate' });
    await row.getByRole('button', { name: 'Set as Household Head', exact: true }).click();
    const dialog = page.locator('.fi-modal-window:visible');
    const roles = dialog.getByRole('combobox', { name: /^Relationship for / });
    for (const field of await roles.all()) if (!(await field.isDisabled())) await field.selectOption('Other');
    await dialog.evaluate(node => { (node.closest('form') || node.querySelector('form')).noValidate = true; });
    await dialog.getByRole('button', { name: 'Submit', exact: true }).click();
    await expect(page.locator('.swal2-popup')).toContainText('Please review the form');
    await expect(page.locator('.swal2-success')).toHaveCount(0);
    await page.locator('.swal2-popup').getByRole('button', { name: 'OK', exact: true }).click();
    await dialog.getByRole('button', { name: 'Cancel', exact: true }).click();
    await page.getByRole('button', { name: 'Clear Household Head', exact: true }).click();
    let clear = page.locator('.fi-modal-window:visible');
    await expect(clear).toContainText('Historical memberships will stay unchanged');
    await clear.getByRole('button', { name: 'Cancel', exact: true }).click();
    await expect(page.getByRole('row').filter({ hasText: 'Head Browser Second' })).toContainText('Head');
    await page.getByRole('button', { name: 'Clear Household Head', exact: true }).click();
    clear = page.locator('.fi-modal-window:visible');
    await clear.getByRole('button', { name: 'Confirm', exact: true }).click();
    await expect(page.locator('.swal2-popup')).toContainText('Household Head Cleared Successfully!');
    await page.locator('.swal2-popup').getByRole('button', { name: 'OK', exact: true }).click();
    await page.reload({ waitUntil: 'domcontentloaded' });
    await expect(page.getByText('No household head assigned. Relationships require HOA review.', { exact: true })).toBeVisible();
});
