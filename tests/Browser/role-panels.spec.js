import AxeBuilder from '@axe-core/playwright';
import { expect, test } from '@playwright/test';

const roles = [
    {
        name: 'Admin',
        panel: 'admin',
        emailEnv: 'HOA_BROWSER_ADMIN_EMAIL',
        passwordEnv: 'HOA_BROWSER_ADMIN_PASSWORD',
        forbiddenPanels: ['staff', 'homeowner'],
    },
    {
        name: 'Staff',
        panel: 'staff',
        emailEnv: 'HOA_BROWSER_STAFF_EMAIL',
        passwordEnv: 'HOA_BROWSER_STAFF_PASSWORD',
        forbiddenPanels: ['admin', 'homeowner'],
    },
    {
        name: 'Homeowner',
        panel: 'homeowner',
        emailEnv: 'HOA_BROWSER_HOMEOWNER_EMAIL',
        passwordEnv: 'HOA_BROWSER_HOMEOWNER_PASSWORD',
        forbiddenPanels: ['admin', 'staff'],
    },
];

const viewports = [
    { width: 320, height: 700 },
    { width: 768, height: 1024 },
    { width: 1440, height: 900 },
    { width: 1920, height: 1080 },
];

async function tabTo(page, locator, maximumTabs = 30, key = 'Tab') {
    for (let index = 0; index < maximumTabs; index += 1) {
        if (await locator.evaluate((element) => element === document.activeElement)) {
            return;
        }

        await page.keyboard.press(key);
    }

    expect(await locator.evaluate((element) => element === document.activeElement),
        `Control was not reachable within ${maximumTabs} Tab presses`).toBe(true);
}

async function expectVisibleFocus(locator) {
    const hasIndicator = await locator.evaluate((element) => {
        let candidate = element;

        for (let level = 0; candidate && level < 4; level += 1) {
            const style = getComputedStyle(candidate);
            const outlineWidth = Number.parseFloat(style.outlineWidth || '0');

            if ((style.outlineStyle !== 'none' && outlineWidth > 0)
                || (style.boxShadow !== 'none' && style.boxShadow !== '')) {
                return true;
            }

            candidate = candidate.parentElement;
        }

        return false;
    });

    expect(hasIndicator, 'Focused control must have a visible outline or focus ring').toBe(true);
}

for (const role of roles) {
    test(`${role.name} panel is keyboard accessible, isolated, and responsive`, async ({ page, browserName }) => {
        test.setTimeout(90_000);

        const email = process.env[role.emailEnv];
        const password = process.env[role.passwordEnv];

        test.skip(!email || !password, `Requires designated ${role.name} browser-test credentials.`);

        await page.setViewportSize(viewports[0]);
        await page.goto(`/${role.panel}/login`, { waitUntil: 'domcontentloaded' });
        await page.waitForLoadState('networkidle');

        const emailInput = page.getByLabel('Email address');
        const passwordInput = page.locator('input[type="password"]');
        const signIn = page.getByRole('button', { name: 'Sign in', exact: true });

        await tabTo(page, emailInput);
        await expectVisibleFocus(emailInput);
        await page.keyboard.insertText(email);
        await expect(emailInput).toHaveValue(email);

        await tabTo(page, passwordInput);
        await expectVisibleFocus(passwordInput);
        await page.keyboard.insertText(password);
        await expect(passwordInput).toHaveValue(password);

        await tabTo(page, signIn);
        await expectVisibleFocus(signIn);
        await page.keyboard.press('Enter');
        await expect(page).toHaveURL(new RegExp(`/${role.panel}/?$`), { timeout: 60_000 });

        const skipLink = page.locator('a.fi-skip-link');
        if (browserName === 'webkit') {
            // Playwright's Windows-hosted WebKit follows Safari's default setting
            // that omits links from Tab order unless full keyboard access is enabled.
            // Focus the link as test setup, then verify its visible focus and keyboard activation.
            await skipLink.focus();
        } else {
            await tabTo(page, skipLink, 100);
        }
        await expect(skipLink).toBeFocused();
        await expect(skipLink).toBeVisible();
        await expectVisibleFocus(skipLink);

        const target = await skipLink.getAttribute('href');
        expect(target).toMatch(/^#[A-Za-z][\w:.-]*$/);
        await page.keyboard.press('Enter');
        await expect(page.locator(target)).toBeVisible();

        const accessibility = await new AxeBuilder({ page })
            .withTags(['wcag2a', 'wcag2aa', 'wcag21aa', 'wcag22aa'])
            .analyze();
        expect(accessibility.violations.filter((violation) => ['serious', 'critical'].includes(violation.impact))).toEqual([]);

        for (const viewport of viewports) {
            await page.setViewportSize(viewport);
            const dimensions = await page.evaluate(() => ({
                clientWidth: document.documentElement.clientWidth,
                scrollWidth: document.documentElement.scrollWidth,
            }));
            expect(dimensions.scrollWidth, `${role.name} overflow at ${viewport.width}px`).toBeLessThanOrEqual(dimensions.clientWidth + 1);
        }

        for (const forbiddenPanel of role.forbiddenPanels) {
            const response = await page.goto(`/${forbiddenPanel}`, { waitUntil: 'domcontentloaded' });
            expect(response?.status()).toBe(403);
        }
    });
}
