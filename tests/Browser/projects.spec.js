import { expect as baseExpect, test } from '@playwright/test';
import path from 'node:path';

const expect = baseExpect.configure({ timeout: 60_000 });
test.beforeEach(async ({ page }) => {
    test.skip(process.env.HOA_PROJECT_BROWSER !== 'true', 'Requires the isolated project runner.');
    test.setTimeout(240_000);
    await page.route('**/*', route => new URL(route.request().url()).origin === new URL(process.env.PLAYWRIGHT_BASE_URL).origin ? route.continue() : route.abort());
    await page.route('**/login', async route => {
        if (route.request().method() !== 'POST') return route.fallback();
        const response = await route.fetch({ maxRedirects: 0 });
        await route.fulfill({ response, headers: { ...response.headers(), location: new URL('/admin/projects', process.env.PLAYWRIGHT_BASE_URL).href } });
    });
    await page.goto('/login');
    await page.getByLabel('Email address').fill('admin@alerts.example.test');
    await page.locator('input[type="password"]').fill('AlertFixture!2026');
    await page.locator('button[type="submit"]').click();
    await expect(page.locator('.swal2-popup')).toContainText('Login Successful!');
    await page.locator('.swal2-confirm').click();
    await expect(page.getByRole('button', { name: 'Add project', exact: true })).toBeVisible();
});

async function saved(page, title) {
    await expect(page.locator('.swal2-popup')).toContainText(title);
    await expect(page.locator('.swal2-popup')).toHaveCount(1);
    await page.locator('.swal2-confirm').click();
}

for (const width of [1280, 390]) {
    test(`unfinished projects save and publish controls react at ${width}px`, async ({ page }) => {
        await page.setViewportSize({ width, height: 900 });
        await page.getByRole('button', { name: 'Add project', exact: true }).click();
        const dialog = page.locator('.fi-modal-window:visible');
        await expect(dialog).toBeVisible();
        const status = dialog.getByRole('combobox', { name: /^Completion status/ });
        const publish = dialog.getByRole('switch', { name: /^Publish/ });
        await expect(publish).toBeDisabled();
        await dialog.getByRole('textbox', { name: /^Title/ }).fill(`Private browser ${width}`);
        await dialog.getByRole('textbox', { name: /^Description/ }).fill('Retained synthetic project description.');
        await dialog.locator('input[type="date"]').fill('2026-01-01');
        if (width === 1280) {
            await dialog.locator('input[type="file"]').setInputFiles(path.resolve('public/images/hoa-hero.webp'));
            await expect(dialog.locator('[data-filepond-item-state="processing-complete"]')).toHaveCount(1);
        }
        await status.selectOption('Completed');
        await expect(publish).toBeEnabled();
        await publish.click();
        await expect(publish).toBeChecked();
        await status.selectOption('In Progress');
        await expect(publish).toBeDisabled();
        await expect(publish).not.toBeChecked();
        await status.selectOption('Completed');
        await expect(publish).not.toBeChecked();
        await status.selectOption('Planned');
        await expect(dialog.getByRole('textbox', { name: /^Title/ })).toHaveValue(`Private browser ${width}`);
        await dialog.getByRole('button', { name: 'Save Project', exact: true }).click();
        await saved(page, 'Project Created Successfully!');
        const row = page.getByRole('row').filter({ hasText: `Private browser ${width}` });
        await expect(row).toContainText('Planned');
        await expect(row).toContainText('Unpublished');
        await row.getByRole('button', { name: 'Edit', exact: true }).click();
        await status.selectOption('Completed');
        await publish.click();
        await dialog.getByRole('button', { name: 'Save Project', exact: true }).click();
        await saved(page, 'Project Updated Successfully!');
        await expect(row).toContainText('Published');
        await row.getByRole('button', { name: 'Edit', exact: true }).click();
        await status.selectOption('In Progress');
        const confirmation = dialog.getByRole('checkbox', { name: /I confirm that saving this unfinished project/ });
        await expect(confirmation).toBeVisible();
        await page.screenshot({ path: path.join(process.env.HOA_PROJECT_REVIEW_DIR, `project-confirmation-${width}.png`) });
        await dialog.getByRole('button', { name: 'Cancel', exact: true }).click();
        await expect(row).toContainText('Completed');
        await expect(row).toContainText('Published');
        await row.getByRole('button', { name: 'Edit', exact: true }).click();
        await status.selectOption('In Progress');
        await confirmation.check();
        await dialog.getByRole('button', { name: 'Save Project', exact: true }).click();
        await saved(page, 'Project Updated Successfully!');
        await expect(row).toContainText('In Progress');
        await expect(row).toContainText('Unpublished');
    });
}
