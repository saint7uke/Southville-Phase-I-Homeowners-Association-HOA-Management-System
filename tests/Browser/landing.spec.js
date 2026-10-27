import AxeBuilder from '@axe-core/playwright';
import { expect, test } from '@playwright/test';

test('landing page has no serious automated accessibility violations', async ({ page }) => {
    await page.goto('/', { waitUntil: 'networkidle' });
    await expect(page.getByRole('heading', { level: 1 })).toBeVisible();
    const brandLogo = page.locator('.hoa-navbar .hoa-brand-mark img');
    await expect(brandLogo).toBeVisible();
    await expect(brandLogo).toHaveAttribute('src', /images\/HOA\.png$/);
    await expect.poll(() => brandLogo.evaluate((image) => image.naturalWidth)).toBeGreaterThan(0);
    await expect(page.locator('link[rel="icon"]')).toHaveAttribute('href', /images\/HOA\.png$/);
    await expect(page.locator('#how-it-works')).toContainText('Register');
    await expect(page.locator('#how-it-works')).toContainText('Verify');
    await expect(page.locator('#how-it-works')).toContainText('Access your panel');
    await expect(page.locator('#how-it-works')).toContainText('Manage everything');
    await expect.poll(() => page.locator('[data-hero-reveal]').last().evaluate((element) => getComputedStyle(element).opacity)).toBe('1');

    const results = await new AxeBuilder({ page }).withTags(['wcag2a', 'wcag2aa', 'wcag21aa', 'wcag22aa']).analyze();
    expect(results.violations.filter((violation) => ['serious', 'critical'].includes(violation.impact))).toEqual([]);
});

for (const viewport of [
    { width: 320, height: 700 },
    { width: 768, height: 1024 },
    { width: 1440, height: 900 },
    { width: 1920, height: 1080 },
]) {
    test(`landing page reflows without horizontal overflow at ${viewport.width}px`, async ({ page }) => {
        await page.setViewportSize(viewport);
        await page.goto('/', { waitUntil: 'networkidle' });
        await expect(page.getByRole('link', { name: /resident login|open resident portal/i }).first()).toBeVisible();

        const dimensions = await page.evaluate(() => ({
            clientWidth: document.documentElement.clientWidth,
            scrollWidth: document.documentElement.scrollWidth,
        }));
        expect(dimensions.scrollWidth).toBeLessThanOrEqual(dimensions.clientWidth + 1);
    });
}

for (const viewport of [
    { width: 320, height: 700 },
    { width: 1440, height: 900 },
]) {
    test(`registration address availability is debounced and accessible at ${viewport.width}px`, async ({ page }) => {
        test.setTimeout(45_000);
        let requestCount = 0;
        await page.setViewportSize(viewport);
        await page.route('**/homeowner/register/address-availability', async (route) => {
            requestCount += 1;
            const body = route.request().postDataJSON();
            const available = !body.street.toLowerCase().includes('reserved');
            await new Promise((resolve) => setTimeout(resolve, 500));
            await route.fulfill({
                contentType: 'application/json',
                body: JSON.stringify({
                    available,
                    message: available
                        ? 'This address is available.'
                        : 'This address is already registered. Please use a different address.',
                }),
            });
        });
        await page.route('**/homeowner/register/email-availability?**', (route) => route.fulfill({
            contentType: 'application/json',
            body: JSON.stringify({ available: true }),
        }));
        await page.goto('/homeowner/register', { waitUntil: 'networkidle' });
        await page.locator('[data-role-card="Owner"]').click();

        await page.locator('#phase_suffix').fill('A');
        await page.locator('#street').fill('Narra Street');
        await page.locator('#block').fill('7');
        await page.locator('#lot').fill('8');

        const status = page.locator('#address-availability-message');
        await expect(status).toHaveText('Checking address availability…');
        await expect(status).toHaveText('This address is available.');
        await expect(status).toHaveAttribute('data-state', 'success');
        expect(requestCount).toBe(1);

        await page.locator('#street').fill('Reserved');
        await page.locator('#street').fill('Reserved Road');
        await page.locator('#street').fill('Reserved Street');
        await expect(status).toHaveText('This address is already registered. Please use a different address.');
        await expect(status).toHaveAttribute('data-state', 'error');
        await expect(page.locator('#phase_suffix')).toHaveAttribute('aria-invalid', 'true');
        expect(requestCount).toBe(2);

        await page.locator('#first_name').fill('Test');
        await page.locator('#last_name').fill('Resident');
        await page.locator('#sex').selectOption('Male');
        await page.locator('#date_of_birth').fill('1990-01-01');
        await page.locator('#contact_number').fill('09171234567');
        await page.locator('#email').fill(`address-check-${viewport.width}@example.test`);
        await page.locator('#password').fill('ResidentPass!2026');
        await page.locator('#password_confirmation').fill('ResidentPass!2026');
        await page.locator('#residency_date').fill('2020-01-01');
        await page.locator('#privacy_consent').check();
        await page.locator('[data-review-registration]').dispatchEvent('submit');
        await expect(page.locator('#phase_suffix')).toBeFocused();
        await expect(page).toHaveURL(/\/homeowner\/register$/);
        expect(requestCount).toBe(3);

        const accessibility = await new AxeBuilder({ page })
            .include('#address-availability-message')
            .withTags(['wcag2a', 'wcag2aa', 'wcag21aa', 'wcag22aa'])
            .analyze();
        expect(accessibility.violations).toEqual([]);

        const dimensions = await page.evaluate(() => ({
            clientWidth: document.documentElement.clientWidth,
            scrollWidth: document.documentElement.scrollWidth,
        }));
        expect(dimensions.scrollWidth).toBeLessThanOrEqual(dimensions.clientWidth + 1);
    });
}

for (const viewport of [
    { width: 320, height: 700 },
    { width: 1440, height: 900 },
]) {
    test(`tenant landlord selection is address scoped and accessible at ${viewport.width}px`, async ({ page }) => {
        test.setTimeout(45_000);
        let landlordRequestCount = 0;
        await page.setViewportSize(viewport);
        await page.route('**/homeowner/register/address-availability', (route) => route.fulfill({
            contentType: 'application/json',
            body: JSON.stringify({ available: true, message: 'This address can be used for a Tenant application.' }),
        }));
        await page.route('**/homeowner/register/email-availability?**', (route) => route.fulfill({
            contentType: 'application/json',
            body: JSON.stringify({ available: true }),
        }));
        await page.route('**/homeowner/register/landlord-availability', async (route) => {
            landlordRequestCount += 1;
            const body = route.request().postDataJSON();
            await new Promise((resolve) => setTimeout(resolve, 800));
            await route.fulfill({
                contentType: 'application/json',
                body: JSON.stringify({
                    options: body.lot === '8'
                        ? [{ label: 'Registered Owner', token: 'opaque-landlord-token' }]
                        : [],
                    message: body.lot === '8'
                        ? 'Select your registered landlord.'
                        : 'No registered landlord was found for this property. Check the address or contact the HOA.',
                }),
            });
        });

        await page.goto('/homeowner/register', { waitUntil: 'networkidle' });
        await page.locator('[data-role-card="Tenant"]').click();
        await page.locator('#phase_suffix').fill('A');
        await page.locator('#street').fill('Narra Street');
        await page.locator('#block').fill('7');
        await page.locator('#lot').fill('8');
        await expect(page.locator('#address-availability-message')).toHaveAttribute('data-state', 'success');

        const landlord = page.locator('#landlord_selector');
        const status = page.locator('#landlord-availability-message');
        await expect(status).toHaveText('Loading registered landlords…');
        await expect(landlord).toContainText('Registered Owner');
        await expect(status).toHaveText('Select your registered landlord.');
        await landlord.selectOption('opaque-landlord-token');
        await expect(status).toHaveText('Registered landlord selected.');
        await expect(status).toHaveAttribute('data-state', 'success');
        await expect(page.locator('#landlord_name')).toHaveValue('Registered Owner');
        await expect(page.locator('#landlord_token')).toHaveValue('opaque-landlord-token');
        expect(landlordRequestCount).toBe(1);

        await page.locator('#lot').fill('9');
        await expect(status).toHaveText('No registered landlord was found for this property. Check the address or contact the HOA.');
        await expect(status).toHaveAttribute('data-state', 'error');
        await expect(landlord).toHaveAttribute('aria-invalid', 'true');
        await expect(page.locator('#landlord_name')).toHaveValue('');
        await expect(page.locator('#landlord_token')).toHaveValue('');
        expect(landlordRequestCount).toBe(2);

        await page.locator('#first_name').fill('Test');
        await page.locator('#last_name').fill('Tenant');
        await page.locator('#sex').selectOption('Male');
        await page.locator('#date_of_birth').fill('1990-01-01');
        await page.locator('#contact_number').fill('09171234567');
        await page.locator('#email').fill(`tenant-landlord-${viewport.width}@example.test`);
        await page.locator('#password').fill('ResidentPass!2026');
        await page.locator('#password_confirmation').fill('ResidentPass!2026');
        await page.locator('#landlord_contact').fill('09181234567');
        await page.locator('#lease_start_date').fill('2026-01-01');
        await page.locator('#lease_end_date').fill('2027-01-01');
        await page.locator('#privacy_consent').check();
        await page.locator('[data-review-registration]').dispatchEvent('submit');
        await expect(landlord).toBeFocused();
        await expect(page).toHaveURL(/\/homeowner\/register$/);

        const accessibility = await new AxeBuilder({ page })
            .include('#landlord-availability-message')
            .withTags(['wcag2a', 'wcag2aa', 'wcag21aa', 'wcag22aa'])
            .analyze();
        expect(accessibility.violations).toEqual([]);

        const dimensions = await page.evaluate(() => ({
            clientWidth: document.documentElement.clientWidth,
            scrollWidth: document.documentElement.scrollWidth,
        }));
        expect(dimensions.scrollWidth).toBeLessThanOrEqual(dimensions.clientWidth + 1);
    });
}

test('reduced-motion preference keeps content available', async ({ page }) => {
    await page.emulateMedia({ reducedMotion: 'reduce' });
    await page.goto('/', { waitUntil: 'networkidle' });
    await expect(page.locator('#contact')).toBeAttached();
    await expect(page.getByRole('button', { name: 'Send message' })).toBeVisible();
});

for (const viewport of [
    { width: 320, height: 700 },
    { width: 1440, height: 900 },
]) {
    test(`registration email availability success is accessible at ${viewport.width}px`, async ({ page }) => {
        await page.setViewportSize(viewport);
        let releaseAvailability;
        const availabilityResponse = new Promise((resolve) => { releaseAvailability = resolve; });
        await page.route('**/homeowner/register/email-availability?**', async (route) => {
            await availabilityResponse;
            await route.fulfill({
                contentType: 'application/json',
                body: JSON.stringify({ available: !route.request().url().includes('reserved') }),
            });
        });
        await page.goto('/homeowner/register', { waitUntil: 'networkidle' });
        await page.locator('[data-role-card="Owner"]').click();

        const email = page.locator('#email');
        await email.fill(`available-${viewport.width}@example.test`);

        const status = page.locator('#email-availability-message');
        await expect(status).toBeVisible();
        await expect(status).toHaveText('Checking email availability…');
        await expect(status).toHaveAttribute('data-state', 'neutral');
        releaseAvailability();
        await expect(status).toHaveText('Email address is available.');
        await expect(status).toHaveAttribute('data-state', 'success');
        await expect(email).toHaveClass(/is-valid/);

        const accessibility = await new AxeBuilder({ page })
            .include('#email-availability-message')
            .withTags(['wcag2a', 'wcag2aa', 'wcag21aa', 'wcag22aa'])
            .analyze();
        expect(accessibility.violations).toEqual([]);

        const dimensions = await page.evaluate(() => ({
            clientWidth: document.documentElement.clientWidth,
            scrollWidth: document.documentElement.scrollWidth,
        }));
        expect(dimensions.scrollWidth).toBeLessThanOrEqual(dimensions.clientWidth + 1);

        await email.fill(`reserved-${viewport.width}@example.test`);
        await expect(status).toHaveText('This email address is already registered or reserved.');
        await expect(status).toHaveAttribute('data-state', 'error');
        await expect(email).toHaveAttribute('aria-invalid', 'true');
    });
}
