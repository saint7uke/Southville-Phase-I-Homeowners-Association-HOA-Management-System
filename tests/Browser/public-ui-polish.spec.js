import AxeBuilder from '@axe-core/playwright';
import { expect, test } from '@playwright/test';
import fs from 'node:fs';

const filipino = JSON.parse(fs.readFileSync('lang/fil.json', 'utf8'));
const icons = ['banknotes', 'megaphone', 'clipboard-document-list', 'exclamation-triangle', 'identification', 'chat-bubble-left-right'];
test.use({ reducedMotion: 'reduce' });

test.beforeEach(async ({ baseURL }) => {
    test.skip(process.env.HOA_BROWSER_PUBLIC_UI_POLISH !== 'true', 'Use the isolated public UI browser runner.');
    expect(new URL(baseURL).hostname).toBe('127.0.0.1');
    expect(process.env.HOA_UI_TEST_DATABASE).toMatch(/public-ui-.*test\.sqlite$/);
    expect(fs.existsSync(process.env.HOA_UI_TEST_DATABASE)).toBe(true);
    test.setTimeout(120_000);
});

async function ready(page, url) {
    await page.goto(url, { waitUntil: 'networkidle' });
    await expect(page.locator('html')).toHaveAttribute('data-locale-ready', 'true');
}

async function language(page, locale) {
    if (await page.locator('#hoa-locale').inputValue() !== locale) await page.locator('#hoa-locale').selectOption(locale);
    await expect(page.locator('html')).toHaveAttribute('lang', locale);
    await expect(page.locator('#hoa-locale')).toBeEnabled();
}

async function accessible(page, selector) {
    const result = await new AxeBuilder({ page }).include(selector).withTags(['wcag2a', 'wcag2aa', 'wcag21aa', 'wcag22aa']).analyze();
    expect(result.violations.filter(issue => ['serious', 'critical'].includes(issue.impact))).toEqual([]);
}

async function noOverflow(page) {
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1)).toBe(true);
}

for (const [width, columns] of [[320, 1], [768, 2], [1440, 3]]) {
    test(`services have six distinct icons and a balanced localized grid at ${width}px`, async ({ page }, testInfo) => {
        await page.setViewportSize({ width, height: 1000 });
        await ready(page, '/');
        const cards = page.locator('#services .hoa-service-card');
        await expect(cards).toHaveCount(6);
        await page.locator('#services').scrollIntoViewIfNeeded();
        expect(await page.locator('#services svg').evaluateAll(nodes => nodes.map(node => node.dataset.serviceIcon))).toEqual(icons);
        expect(await page.locator('#services path').evaluateAll(nodes => new Set(nodes.map(node => node.getAttribute('d'))).size)).toBe(6);
        await expect(cards.locator('a, button, [tabindex]')).toHaveCount(0);
        for (const svg of await cards.locator('svg').all()) {
            await expect(svg).toHaveAttribute('aria-hidden', 'true');
            await expect(svg).toHaveAttribute('focusable', 'false');
            expect(await svg.evaluate(node => node.getBBox().width)).toBeGreaterThan(0);
            expect(await svg.evaluate(node => node.getBBox().height)).toBeGreaterThan(0);
        }
        for (const locale of ['en', 'fil']) {
            await language(page, locale);
            await expect(cards.first().getByRole('heading')).toHaveText(locale === 'en' ? 'HOA dues and payments' : filipino['HOA dues and payments']);
            const boxes = await cards.evaluateAll(nodes => nodes.map(node => {
                const box = node.getBoundingClientRect();
                return { top: Math.round(box.top), width: box.width, height: box.height, overflow: node.scrollWidth > node.clientWidth + 1 };
            }));
            expect(new Set(boxes.map(box => box.top)).size).toBe(6 / columns);
            expect(Math.max(...boxes.map(box => box.width)) - Math.min(...boxes.map(box => box.width))).toBeLessThanOrEqual(1);
            expect(boxes.some(box => box.overflow)).toBe(false);
            for (let index = 0; index < 6; index += columns) {
                expect(Math.max(...boxes.slice(index, index + columns).map(box => box.height)) - Math.min(...boxes.slice(index, index + columns).map(box => box.height))).toBeLessThanOrEqual(1);
            }
            for (const colorScheme of ['light', 'dark']) {
                await page.emulateMedia({ colorScheme });
                await accessible(page, '#services');
            }
            await noOverflow(page);
            await page.locator('#services').screenshot({ path: testInfo.outputPath(`services-${locale}-${width}.png`) });
        }
    });

    test(`login and registration branding preserve keyboard and unsaved state at ${width}px`, async ({ page }, testInfo) => {
        await page.setViewportSize({ width, height: 1000 });
        await ready(page, '/login');
        await page.locator('#email').fill('unsaved-ui@example.test');
        await page.locator('#email').focus();
        await page.keyboard.press('Tab');
        await expect(page.locator('#password')).toBeFocused();
        await page.locator('#password').fill('SyntheticUiPass!2026');
        for (const locale of ['fil', 'en']) {
            await language(page, locale);
            await expect(page.locator('.hoa-portal-auth-links')).not.toContainText('For homeowners, staff, and administrators');
            await expect(page.locator('.hoa-portal-auth-links')).not.toContainText(filipino['For homeowners, staff, and administrators']);
            expect(new URL(await page.locator('.hoa-portal-auth-links > :last-child').getAttribute('href')).pathname).toBe('/');
            await expect(page.locator('#email')).toHaveValue('unsaved-ui@example.test');
            await expect(page.locator('#password')).toHaveValue('SyntheticUiPass!2026');
        }
        await accessible(page, '.hoa-portal-auth-form');
        await noOverflow(page);
        await ready(page, '/homeowner/register');
        const badge = page.locator('.hoa-auth-brand .hoa-brand-mark');
        const logo = badge.locator('img');
        await expect(logo).toBeVisible();
        await expect(logo).toHaveAttribute('src', /\/images\/HOA\.png$/);
        await expect.poll(() => logo.evaluate(node => node.naturalWidth)).toBeGreaterThan(0);
        expect((await badge.textContent()).trim()).toBe('');
        expect(Math.round((await badge.boundingBox()).width)).toBe(48);
        expect(Math.round((await badge.boundingBox()).height)).toBe(48);
        const owner = page.locator('[data-role-card="Owner"]');
        await owner.focus();
        await page.keyboard.press('Enter');
        await expect(page.locator('#step-2')).toHaveClass(/is-active/);
        await page.locator('#first_name').fill('Unchanged');
        await page.locator('#phase_suffix').fill('a7');
        await page.locator('#phase_suffix').blur();
        await page.locator('#password').fill('weak');
        await expect(page.locator('#password-requirements li.is-invalid')).not.toHaveCount(0);
        await page.locator('#password').fill('SyntheticUiPass!2026');
        await expect(page.locator('#password-requirements li.is-valid')).toHaveCount(5);
        await page.locator('#verification_document').setInputFiles({ name: 'unsaved-ui-proof.pdf', mimeType: 'application/pdf', buffer: Buffer.from('%PDF-1.4 synthetic unsaved test only') });
        await language(page, 'fil');
        await expect(page.locator('#first_name')).toHaveValue('Unchanged');
        await expect(page.locator('#phase_suffix')).toHaveValue('A7');
        await expect(page.locator('#password')).toHaveValue('SyntheticUiPass!2026');
        expect(await page.locator('#verification_document').evaluate(node => node.files[0].name)).toBe('unsaved-ui-proof.pdf');
        await expect(page.locator('#step-2')).toHaveClass(/is-active/);
        for (const colorScheme of ['light', 'dark']) {
            await page.emulateMedia({ colorScheme });
            await accessible(page, '#auth-card');
        }
        await page.locator('#step-2 button[onclick="goBack()"]').click();
        await expect(page.locator('#step-1')).toHaveClass(/is-active/);
        await owner.click();
        await expect(page.locator('#first_name')).toHaveValue('Unchanged');
        let submissions = 0;
        page.on('request', request => { if (request.method() === 'POST' && new URL(request.url()).pathname === '/homeowner/register') submissions++; });
        await page.locator('#step-2 button[type="submit"]').click();
        await expect(page.locator('#last_name')).toBeFocused();
        expect(submissions).toBe(0);
        await noOverflow(page);
        await page.locator('#auth-card').screenshot({ path: testInfo.outputPath(`registration-${width}.png`) });
    });
}

test('service reveal, navigation and long text remain usable without fake controls', async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 1000 });
    await page.emulateMedia({ reducedMotion: 'no-preference' });
    await ready(page, '/');
    const services = page.locator('a[href="#services"]').first();
    await services.focus();
    await page.keyboard.press('Enter');
    await expect(page).toHaveURL(/#services$/);
    await expect(page.locator('#services .hoa-service-card').last()).toBeVisible();
    await expect.poll(() => page.locator('#services .hoa-service-card').last().evaluate(node => getComputedStyle(node).opacity)).toBe('1');
    await language(page, 'fil');
    await page.locator('#services .hoa-service-card').first().evaluate(node => {
        node.querySelector('h3').textContent = 'Mahabang pamagat para sa serbisyo '.repeat(12);
        node.querySelector('p').textContent = 'Paliwanag para sa mga residente at kanilang sambahayan. '.repeat(20);
    });
    await page.setViewportSize({ width: 320, height: 1000 });
    const card = page.locator('#services .hoa-service-card').first();
    expect(await card.evaluate(node => node.scrollHeight <= node.clientHeight + 1 && node.scrollWidth <= node.clientWidth + 1)).toBe(true);
    await noOverflow(page);
});
