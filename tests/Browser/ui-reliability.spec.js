import { test, expect } from '@playwright/test';

async function login(page, account = 'admin') {
    await page.goto('/login', { waitUntil: 'domcontentloaded' });
    await page.getByLabel('Email address').fill(`${account}@alerts.example.test`);
    await page.locator('input[type="password"]').fill('AlertFixture!2026');
    await page.locator('button[type="submit"]').click();
    await expect(page).toHaveURL(new RegExp(`/${account === 'resident' ? 'homeowner' : account}/?$`));
    await expect(page.locator('.swal2-popup')).toContainText('Login Successful!');
    await page.locator('.swal2-popup').getByRole('button', { name: 'OK', exact: true }).click();
    await expect(page.locator('.swal2-popup')).toHaveCount(0);
}

test.beforeEach(() => {
    test.skip(process.env.HOA_ALERT_BROWSER_FIXTURES !== 'true', 'Requires the isolated UI runner.');
    test.setTimeout(180_000);
});

test('column checkboxes apply, persist across sessions, and reset to defaults', async ({ page, browser }) => {
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));
    await login(page);
    await page.goto('/admin/users', { waitUntil: 'domcontentloaded' });
    await page.getByRole('button', { name: /column manager/i }).click();
    const manager = page.locator('.fi-ta-col-manager:visible');
    await expect(manager.getByLabel('Name', { exact: true })).toBeDisabled();
    await manager.getByLabel('Email', { exact: true }).uncheck();
    await expect(page.getByRole('columnheader', { name: 'Email', exact: true })).toBeVisible();
    await manager.getByRole('button', { name: /apply columns/i }).click({ timeout: 15_000 });
    await expect(page.getByRole('columnheader', { name: 'Email', exact: true })).toHaveCount(0);
    await page.reload({ waitUntil: 'domcontentloaded' });
    await expect(page.getByRole('columnheader', { name: 'Email', exact: true })).toHaveCount(0);
    const context = await browser.newContext();
    try {
        const otherSession = await context.newPage();
        await login(otherSession);
        await otherSession.goto('/admin/users', { waitUntil: 'domcontentloaded' });
        await expect(otherSession.getByRole('columnheader', { name: 'Email', exact: true })).toHaveCount(0);
        await otherSession.getByRole('button', { name: /column manager/i }).click();
        await otherSession.locator('.fi-ta-col-manager:visible').getByRole('button', { name: 'Reset to Default', exact: true }).click();
        await expect(otherSession.getByRole('columnheader', { name: 'Email', exact: true })).toBeVisible();
    } finally { await context.close(); }
    expect(errors).toEqual([]);
});

test('mobile column manager supports keyboard toggling', async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 844 });
    await login(page);
    await page.goto('/admin/users', { waitUntil: 'domcontentloaded' });
    await page.getByRole('button', { name: /column manager/i }).click();
    const manager = page.locator('.fi-ta-col-manager:visible');
    const email = manager.getByLabel('Email', { exact: true });
    await email.focus();
    const checked = await email.isChecked();
    await email.press('Space');
    await expect(email).toBeChecked({ checked: !checked });
    await manager.getByRole('button', { name: /apply columns/i }).click();
    await page.reload({ waitUntil: 'domcontentloaded' });
    await page.getByRole('button', { name: /column manager/i }).click();
    await manager.getByRole('button', { name: 'Reset to Default', exact: true }).click();
});

test('Others specification appears and clears after category changes', async ({ page }) => {
    await login(page, 'resident');
    await page.goto('/homeowner/complaints/create', { waitUntil: 'domcontentloaded' });
    const category = page.getByRole('combobox', { name: /^Category/ });
    await category.selectOption('Others');
    const other = page.getByLabel('Please Specify', { exact: false });
    await expect(other).toBeVisible();
    await other.fill('Disposable example');
    await category.selectOption('Noise');
    await expect(other).toBeHidden();
    await category.selectOption('Others');
    await expect(other).toHaveValue('');
});

test('registration has only the approved phase choices and no Street input', async ({ page }) => {
    await page.goto('/homeowner/register', { waitUntil: 'domcontentloaded' });
    await expect(page.locator('html')).toHaveAttribute('data-hoa-alerts-ready', 'true');
    await page.locator('[data-role-card="Owner"]').click();
    await expect(page.locator('input[name="street"]')).toHaveCount(0);
    const phase = page.locator('select[name="phase_suffix"]');
    await expect(phase).toBeVisible();
    expect(await phase.locator('option').evaluateAll(options => options.map(option => option.value))).toEqual(['', 'A', 'B', 'EXPANSION']);
    await phase.selectOption('EXPANSION');
    await expect(phase).toHaveValue('EXPANSION');
});
