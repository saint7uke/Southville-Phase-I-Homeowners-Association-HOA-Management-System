import AxeBuilder from '@axe-core/playwright';
import { expect, test } from '@playwright/test';

test.beforeEach(async ({ page, baseURL }) => {
    test.skip(process.env.HOA_BROWSER_MY_HOUSEHOLD !== 'true', 'Requires the isolated My Household test runner.');
    expect(new URL(baseURL).hostname).toBe('127.0.0.1');
    expect(new URL(baseURL).port).not.toBe('8000');
    test.setTimeout(240_000);
    page.setDefaultTimeout(30_000);
    page.setDefaultNavigationTimeout(60_000);
});

async function login(page, account = 'large') {
    await page.goto('/login');
    await page.locator('#email').fill(`${account}@household.example.test`);
    await page.locator('#password').fill('SyntheticHousehold!2026');
    await page.locator('button[type="submit"]').click();
    await expect(page).toHaveURL(/\/homeowner\/?$/, { timeout: 60_000 });
    await page.goto('/homeowner/my-household');
    await expect(page.locator('html')).toHaveAttribute('data-locale-ready', 'true');
    await changeLanguage(page, 'en');
}

async function changeLanguage(page, locale) {
    const selector = page.locator('#hoa-locale');
    await expect(selector).toBeEnabled({ timeout: 30_000 });
    if (await selector.inputValue() === locale && await page.locator('html').getAttribute('lang') === locale) return;
    const saved = page.waitForResponse(response => new URL(response.url()).pathname === '/locale' && response.request().method() === 'POST', { timeout: 60_000 });
    await selector.selectOption(locale);
    expect((await saved).ok()).toBe(true);
    await expect(page.locator('html')).toHaveAttribute('lang', locale, { timeout: 30_000 });
}

test('large household paginates with loading feedback, keyboard access, privacy and a full PDF download', async ({ page }) => {
    await login(page);
    await expect(page.getByRole('heading', { name: 'Household overview', exact: true })).toBeVisible();
    await expect(page.locator('.hoa-household__count strong')).toHaveText('25');
    await expect(page.getByText('Member 01 English Name', { exact: true })).toBeVisible();
    await expect(page.getByText('Member 10 English Name', { exact: true })).toHaveCount(0);
    await expect(page.getByText('Ended Secret Member', { exact: true })).toHaveCount(0);
    await expect(page.locator('.hoa-household')).not.toContainText('1999-02-03');
    await expect(page.locator('.hoa-household')).not.toContainText('AAA Unrelated Resident');
    const second = page.locator('.fi-pagination-next-btn');
    await expect(second).toHaveAccessibleName('Next');
    await expect(second).toHaveText('Next');
    await second.focus();
    const changedPage = page.waitForResponse(response => new URL(response.url()).pathname === '/livewire/update' && response.request().method() === 'POST', { timeout: 60_000 });
    await second.press('Enter');
    await expect(page.locator('.hoa-household__loading')).toBeVisible();
    expect((await changedPage).ok()).toBe(true);
    await expect(page.getByText('Member 10 English Name', { exact: true })).toBeVisible({ timeout: 30_000 });
    await expect(page.getByText('Member 01 English Name', { exact: true })).toHaveCount(0);
    await expect(page.locator('.fi-pagination-overview')).toHaveText('Showing 11 to 20 of 25 members');
    await changeLanguage(page, 'fil');
    await expect(page.getByRole('heading', { name: 'Buod ng sambahayan', exact: true })).toBeVisible();
    await expect(page.locator('.fi-pagination-overview')).toHaveText('Ipinapakita ang 11 hanggang 20 sa 25 miyembro');
    await expect(page.getByText('Member 10 English Name', { exact: true })).toBeVisible();
    await expect(page.locator('.fi-pagination-next-btn')).toHaveAccessibleName('Susunod');
    await expect(page.locator('.fi-pagination-next-btn')).toHaveText('Susunod');
    const nextPage = page.waitForResponse(response => new URL(response.url()).pathname === '/livewire/update' && response.request().method() === 'POST', { timeout: 60_000 });
    await page.locator('.fi-pagination-next-btn').press('Enter');
    expect((await nextPage).ok()).toBe(true);
    await expect(page.getByText('Member 24 English Name', { exact: true })).toBeVisible({ timeout: 30_000 });
    await expect(page.locator('.hoa-household__count strong')).toHaveText('25');
    const download = page.waitForEvent('download');
    await page.locator('.hoa-household__download a').click();
    expect((await download).suggestedFilename()).toMatch(/^hoa-household-.+\.pdf$/);
    await expect(page.locator('.hoa-household')).not.toContainText('1999-02-03');
    await expect(page.locator('.hoa-household button').filter({ hasText: /Add member|Edit household|Delete/ })).toHaveCount(0);
});

test('summary and stacked member rows fit all sizes and themes with accessible bilingual copy', async ({ page }, testInfo) => {
    await login(page);
    for (const dark of [false, true]) {
        await page.evaluate(enabled => document.documentElement.classList.toggle('dark', enabled), dark);
        for (const width of [320, 768, 1440]) {
            await page.setViewportSize({ width, height: 1000 });
            await expect(page.getByRole('heading', { name: 'Household overview', exact: true })).toBeVisible();
            expect(await page.evaluate(() => document.documentElement.scrollWidth > innerWidth + 1)).toBe(false);
            const axe = await new AxeBuilder({ page }).include('.hoa-household').withTags(['wcag2a', 'wcag2aa']).analyze();
            expect(axe.violations.filter(issue => ['serious', 'critical'].includes(issue.impact))).toEqual([]);
            await page.evaluate(() => window.scrollTo(0, 0));
            await page.screenshot({ path: testInfo.outputPath(`household-${width}-${dark ? 'dark' : 'light'}.png`), fullPage: true });
        }
    }
    await changeLanguage(page, 'fil');
    await expect(page.getByText('Mga Miyembro ng Sambahayan', { exact: true }).last()).toBeVisible();
    await expect(page.getByText('English Resident Street Kept Unchanged', { exact: false })).toBeVisible();
    await expect(page.getByText('Member 01 English Name', { exact: true })).toBeVisible();
    await page.reload();
    await expect(page.locator('html')).toHaveAttribute('lang', 'fil');
    await expect(page.getByRole('heading', { name: 'Buod ng sambahayan', exact: true })).toBeVisible();
});

test('small households hide pagination and unavailable memberships show safe guidance', async ({ browser, baseURL }) => {
    for (const account of ['small', 'unavailable']) {
        const context = await browser.newContext({ baseURL });
        const page = await context.newPage();
        await login(page, account);
        if (account === 'small') {
            await expect(page.locator('.hoa-household__count strong')).toHaveText('10');
            await expect(page.getByText('Member 09 English Name', { exact: true })).toBeVisible();
            await expect(page.locator('.hoa-household .fi-pagination')).toHaveCount(0);
        } else {
            await expect(page.getByRole('heading', { name: 'Household information unavailable', exact: true })).toBeVisible();
            await expect(page.locator('[data-household-members]')).toHaveCount(0);
            await expect(page.locator('.hoa-household__download')).toHaveCount(0);
        }
        await context.close();
    }
});
