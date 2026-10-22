import AxeBuilder from '@axe-core/playwright';
import { expect, test } from '@playwright/test';

test.beforeEach(async ({ page, baseURL }) => {
    test.skip(process.env.HOA_BROWSER_SERVICE_REQUEST_EDITING !== 'true', 'Requires the guarded ServiceRequestEditingBrowserSeeder and isolated test server.');
    expect(new URL(baseURL).hostname).toMatch(/^(127\.0\.0\.1|localhost)$/);
    test.setTimeout(240_000);
    page.setDefaultTimeout(30_000);
    page.setDefaultNavigationTimeout(90_000);
});

async function login(page, panel) {
    await page.goto('/login');
    await page.locator('#email').fill(`${panel}@request-edit.example.test`);
    await page.locator('#password').fill('BrowserTest!2026');
    await page.locator('button[type="submit"]').click();
    await expect(page).toHaveURL(new RegExp(`/${panel}/?$`), { timeout: 60_000 });
    await page.locator('#hoa-locale').selectOption('en');
    await expect(page.locator('html')).toHaveAttribute('lang', 'en');
}

async function openRequest(page, panel, subject) {
    const response = await page.goto(`/${panel}/service-requests`);
    expect(response.status(), 'The isolated request-list page must load successfully before editing.').toBe(200);
    await page.locator('.fi-ta-search-field input').fill(subject);
    const row = page.getByRole('row').filter({ hasText: subject });
    await expect(row).toHaveCount(1, { timeout: 30_000 });
    await row.getByRole('link', { name: 'Edit', exact: true }).click();
    await expect(page).toHaveURL(new RegExp(`/${panel}/service-requests/\\d+/edit$`));
}

const status = page => page.locator('select[id="data.status"]');
const remarks = page => page.locator('textarea[id="data.admin_remarks"]');
const options = page => status(page).locator('option').evaluateAll(nodes => nodes.map(node => node.value).filter(Boolean));

test('admin and staff can save Processing then Approved, preserve input and refresh correctly', async ({ browser, baseURL }, testInfo) => {
    for (const panel of ['staff', 'admin']) {
        const context = await browser.newContext({ baseURL });
        const current = await context.newPage();
        current.setDefaultTimeout(30_000);
        current.setDefaultNavigationTimeout(90_000);
        await login(current, panel);
        const subject = `Synthetic ${testInfo.project.name} ${panel} approval flow`;
        await openRequest(current, panel, subject);
        await expect.poll(() => options(current)).toEqual(['Pending', 'Processing', 'Rejected']);
        await expect(current.getByText('Save as Processing before approval.', { exact: false })).toBeVisible();
        await remarks(current).fill('Keep these review notes across language changes.');
        await current.locator('#hoa-locale').selectOption('fil');
        await expect(current.getByText('I-save muna bilang Pinoproseso bago aprubahan.', { exact: false })).toBeVisible();
        await expect(remarks(current)).toHaveValue('Keep these review notes across language changes.');
        await current.locator('#hoa-locale').selectOption('en');
        await status(current).selectOption('Processing');
        await current.getByRole('button', { name: 'Save changes', exact: true }).press('Enter');
        await expect.poll(() => options(current), { timeout: 30_000 }).toEqual(['Processing', 'Approved', 'Rejected']);
        await expect(status(current)).toHaveValue('Processing');
        // Approve in the same edit view: no manual refresh required to see the next step.
        await status(current).selectOption('Approved');
        await current.getByRole('button', { name: 'Save changes', exact: true }).click();
        await expect.poll(() => options(current), { timeout: 30_000 }).toEqual(['Approved']);
        await current.reload();
        await expect(status(current)).toHaveValue('Approved');
        await expect(remarks(current)).toHaveValue('Keep these review notes across language changes.');
        await expect(current.getByText('Certificate requests are completed automatically', { exact: false })).toBeVisible();
        for (const width of [320, 768, 1440]) {
            await current.setViewportSize({ width, height: 1000 });
            expect(await current.evaluate(() => document.documentElement.scrollWidth > innerWidth + 1)).toBe(false);
        }
        const axe = await new AxeBuilder({ page: current }).include('.fi-resource-edit-record-page form').withTags(['wcag2a', 'wcag2aa']).analyze();
        expect(axe.violations.filter(issue => ['serious', 'critical'].includes(issue.impact))).toEqual([]);
        await current.screenshot({ path: testInfo.outputPath(`${panel}-approved.png`) });
        await current.goto(`/${panel}/service-requests`);
        await current.locator('.fi-ta-search-field input').fill(subject);
        const row = current.getByRole('row').filter({ hasText: subject });
        await row.getByRole('link', { name: 'Prepare certificate', exact: true }).click();
        await expect(current).toHaveURL(new RegExp(`/${panel}/certificates/create\\?request=\\d+$`));
        await expect(current.getByRole('textbox', { name: /^First resident full name/ })).toHaveValue('Resident Synthetic Fixture');
        await context.close();
    }
});

test('grandfathered non-certificate services retain authorized manual completion', async ({ page }, testInfo) => {
    await login(page, 'staff');
    await openRequest(page, 'staff', `Synthetic ${testInfo.project.name} legacy completion`);
    await expect.poll(() => options(page)).toEqual(['Approved', 'Completed']);
    await status(page).selectOption('Completed');
    await remarks(page).fill('Synthetic repair finished.');
    await page.getByRole('button', { name: 'Save changes', exact: true }).click();
    await expect.poll(() => options(page), { timeout: 30_000 }).toEqual(['Completed']);
    await page.reload();
    await expect(status(page)).toHaveValue('Completed');
    await expect(remarks(page)).toHaveValue('Synthetic repair finished.');
});
