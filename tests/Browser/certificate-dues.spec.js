import { test, expect as baseExpect } from '@playwright/test';
import { execFileSync } from 'node:child_process';

const expect = baseExpect.configure({ timeout: 30_000 });

test.use({ actionTimeout: 20_000, navigationTimeout: 30_000 });
test.beforeEach(async () => {
    test.skip(process.env.HOA_CERTIFICATE_DUES_BROWSER !== 'true', 'Requires isolated certificate dues runner.');
    test.setTimeout(120_000);
});
const warning = 'You have outstanding monthly dues. Please visit the HOA office to settle your balance before requesting a certificate.';
function scalar(code) {
    if (!process.env.DB_DATABASE.includes('hoa-alert-browser-certificate-dues-')) throw new Error('Unsafe fixture database');
    return execFileSync('php', ['-r', `require 'vendor/autoload.php'; $app=require 'bootstrap/app.php'; $app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap(); ${code}`], { encoding: 'utf8', env: process.env }).trim();
}
async function login(page, panel, destination) {
    await page.route('**/*', route => new URL(route.request().url()).origin === new URL(process.env.PLAYWRIGHT_BASE_URL).origin ? route.continue() : route.abort());
    await page.route('**/login', async route => {
        if (route.request().method() !== 'POST') return route.fallback();
        const response = await route.fetch({ maxRedirects: 0 });
        await route.fulfill({ response, headers: { ...response.headers(), location: new URL(destination, process.env.PLAYWRIGHT_BASE_URL).href } });
    });
    await page.goto('/login');
    await page.getByLabel('Email address').fill(`${panel === 'homeowner' ? 'resident' : panel}@alerts.example.test`);
    await page.locator('input[type="password"]').fill('AlertFixture!2026');
    await page.locator('button[type="submit"]').click();
    await expect(page.locator('.swal2-popup')).toContainText('Login Successful!');
    await page.locator('.swal2-popup button.swal2-confirm').click();
}

for (const panel of ['admin', 'staff']) {
    test(`${panel} cannot approve while dues remain unpaid`, async ({ page }) => {
        const id = scalar("echo App\\Models\\ServiceRequest::first()->id;");
        await login(page, panel, `/${panel}/service-requests/${id}/edit`);
        await expect(page.getByText(warning, { exact: true })).toBeVisible();
        await page.getByRole('combobox', { name: /^Status/ }).selectOption('Approved');
        await page.getByRole('button', { name: 'Save changes', exact: true }).click();
        await expect(page.locator('.swal2-popup')).toContainText('Certificate Processing Blocked');
        await expect(page.locator('.swal2-popup')).toHaveCount(1);
        await expect(page.locator('.swal2-success')).toHaveCount(0);
        expect(scalar(`echo App\\Models\\ServiceRequest::find(${id})->status;`)).toBe('Processing');
    });
}

test('homeowner cancellation and blocked submission show no success; settlement restores access', async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 900 });
    await login(page, 'homeowner', '/homeowner/service-requests/create');
    await expect(page.getByText(warning, { exact: true })).toBeVisible();
    await page.getByRole('combobox', { name: /^Request type/ }).selectOption('Certificate of Residency');
    await page.getByRole('textbox', { name: /^Subject/ }).fill('Synthetic homeowner browser request');
    await page.getByRole('textbox', { name: /^Description/ }).fill('Synthetic request only.');
    await page.getByRole('button', { name: 'Create', exact: true }).click();
    let dialog = page.locator('.swal2-popup');
    await expect(dialog).toContainText('Review and submit request');
    await dialog.getByRole('button', { name: 'Cancel', exact: true }).click();
    await expect(page.locator('.swal2-popup')).toHaveCount(0);
    await page.getByRole('button', { name: 'Create', exact: true }).click();
    dialog = page.locator('.swal2-popup');
    await dialog.getByRole('button', { name: /Confirm|Submit|Create/ }).click();
    await expect(page.locator('.swal2-popup')).toContainText('Certificate Processing Blocked');
    await expect(page.locator('.swal2-popup')).toHaveCount(1);
    await expect(page.locator('.swal2-success')).toHaveCount(0);
    expect(scalar('echo App\\Models\\ServiceRequest::count();')).toBe('1');
    await page.locator('.swal2-popup button.swal2-confirm').click();
    scalar("$due=App\\Models\\DuesObligation::first(); $actor=App\\Models\\User::where('email','admin@alerts.example.test')->sole(); app(App\\Actions\\Payments\\RecordPayment::class)->handle(['dues_obligation_id'=>$due->id,'amount_paid'=>'100.00','payment_method'=>'Cash','payment_date'=>today()->toDateString()],$actor);");
    await page.reload();
    await expect(page.getByText('Your assessed monthly dues are settled. You may request a certificate.', { exact: true })).toBeVisible();
    await expect(page.getByText(warning, { exact: true })).toHaveCount(0);
    await page.getByRole('combobox', { name: /^Request type/ }).selectOption('Certificate of Residency');
    await page.getByRole('textbox', { name: /^Subject/ }).fill('Synthetic settled request');
    await page.getByRole('textbox', { name: /^Description/ }).fill('Synthetic settled request only.');
    await page.getByRole('button', { name: 'Create', exact: true }).click();
    await expect(page.locator('.swal2-popup')).toContainText('Review and submit request');
    await page.locator('.swal2-popup').getByRole('button', { name: 'Confirm', exact: true }).click();
    await expect(page.locator('.swal2-popup')).toContainText('Service Request Submitted Successfully!');
    await expect(page.locator('.swal2-popup')).toHaveCount(1);
    expect(scalar('echo App\\Models\\ServiceRequest::count();')).toBe('2');
});
