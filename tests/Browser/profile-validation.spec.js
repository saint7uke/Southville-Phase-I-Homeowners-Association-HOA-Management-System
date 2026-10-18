import AxeBuilder from '@axe-core/playwright';
import { expect, test } from '@playwright/test';

test('homeowner profile validates fields and conflicts responsively', async ({ page }) => {
    test.setTimeout(90_000);

    const email = process.env.HOA_BROWSER_HOMEOWNER_EMAIL;
    const password = process.env.HOA_BROWSER_HOMEOWNER_PASSWORD;
    test.skip(!email || !password, 'Requires designated Homeowner browser-test credentials.');

    let emailRequests = 0;
    let addressRequests = 0;
    await page.route('**/homeowner/my-profile/availability', async (route) => {
        const body = route.request().postDataJSON();
        if (body.field === 'email') {
            emailRequests += 1;
        } else {
            addressRequests += 1;
        }
        await new Promise((resolve) => setTimeout(resolve, 800));
        const valid = body.field === 'email'
            ? !body.email.includes('reserved')
            : !body.street.toLowerCase().includes('reserved');
        await route.fulfill({
            contentType: 'application/json',
            body: JSON.stringify({
                valid,
                message: valid
                    ? body.field === 'email' ? 'This email address is available.' : 'This address is available for your profile.'
                    : body.field === 'email' ? 'This email address is already registered or reserved.' : 'This address is already registered. Please use a different address.',
            }),
        });
    });

    await page.goto('/homeowner/login', { waitUntil: 'networkidle' });
    await page.getByLabel('Email address').fill(email);
    await page.locator('input[type="password"]').fill(password);
    await page.locator('button[type="submit"]').click();
    await expect(page).toHaveURL(/\/homeowner\/?$/, { timeout: 60_000 });
    await page.goto('/homeowner/my-profile', { waitUntil: 'networkidle' });

    const profileForm = page.locator('[data-profile-validation-form]');
    await expect(profileForm).toBeVisible();
    await expect(profileForm.getByLabel('Phase')).toBeDisabled();
    await expect(profileForm.getByLabel('Occupancy type')).toBeDisabled();

    const firstName = profileForm.locator('[data-profile-field-wrapper="first_name"] input');
    await firstName.fill('123');
    await expect(profileForm.locator('[data-realtime-status="first_name"]')).toContainText('Use letters');
    await expect(firstName).toHaveAttribute('aria-invalid', 'true');
    await firstName.fill('María Santos');
    await expect(profileForm.locator('[data-realtime-status="first_name"]')).toContainText('Looks good');

    const phone = profileForm.locator('[data-profile-field-wrapper="contact_number"] input');
    await phone.fill('0917-123-4567');
    await phone.blur();
    await expect(phone).toHaveValue('09171234567');
    await expect(profileForm.locator('[data-realtime-status="contact_number"]')).toContainText('Looks good');

    const profileEmail = profileForm.locator('[data-profile-field-wrapper="email"] input');
    await profileEmail.fill('reserved');
    await profileEmail.fill('reserved@example.test');
    await expect(profileForm.locator('[data-realtime-status="email"]')).toContainText('Checking email availability');
    await expect(profileForm.locator('[data-realtime-status="email"]')).toContainText('already registered or reserved');
    expect(emailRequests).toBe(1);

    await profileEmail.fill('available@example.test');
    await expect(profileForm.locator('[data-realtime-status="email"]')).toContainText('This email address is available.');
    expect(emailRequests).toBe(2);

    const street = profileForm.locator('[data-profile-field-wrapper="street"] input');
    await street.fill('Reserved');
    await street.fill('Reserved Road');
    await street.fill('Reserved Street');
    await expect(profileForm.locator('[data-realtime-status="house_number"]')).toContainText('already registered');
    expect(addressRequests).toBe(1);
    await expect(profileForm.locator('[data-profile-submit]')).toBeDisabled();

    await street.fill('Available Street');
    await expect(profileForm.locator('[data-realtime-status="house_number"]')).toContainText('available for your profile');
    await profileForm.locator('[data-profile-field-wrapper="emergency_contact_name"] input').fill('Emergency Contact');
    await profileForm.locator('[data-profile-field-wrapper="emergency_contact_number"] input').fill('09190000000');
    await expect(profileForm.locator('[data-profile-submit]')).toBeEnabled();

    const passwordForm = page.locator('[data-password-validation-form]');
    await passwordForm.locator('[data-password-field-wrapper="current_password"] input').fill('current-value');
    await passwordForm.locator('[data-password-field-wrapper="password"] input').fill('weak');
    await expect(passwordForm.locator('[data-realtime-status="password-password"]')).toContainText('at least 12 characters');
    await passwordForm.locator('[data-password-field-wrapper="password"] input').fill('StrongPassword!2026');
    await passwordForm.locator('[data-password-field-wrapper="password_confirmation"] input').fill('different');
    await expect(passwordForm.locator('[data-realtime-status="password-password_confirmation"]')).toContainText('does not match');
    await expect(passwordForm.locator('[data-password-submit]')).toBeDisabled();
    await passwordForm.dispatchEvent('submit');
    await expect(passwordForm.locator('[data-client-validation-summary="password"]')).toContainText('does not match');
    await expect(passwordForm.locator('[data-password-field-wrapper="password_confirmation"] input')).toBeFocused();

    for (const viewport of [{ width: 320, height: 700 }, { width: 1440, height: 900 }]) {
        await page.setViewportSize(viewport);
        const dimensions = await page.evaluate(() => ({
            clientWidth: document.documentElement.clientWidth,
            scrollWidth: document.documentElement.scrollWidth,
        }));
        expect(dimensions.scrollWidth).toBeLessThanOrEqual(dimensions.clientWidth + 1);
    }

    const accessibility = await new AxeBuilder({ page })
        .include('[data-profile-validation-form]')
        .withTags(['wcag2a', 'wcag2aa', 'wcag21aa', 'wcag22aa'])
        .analyze();
    expect(accessibility.violations.filter((violation) => ['serious', 'critical'].includes(violation.impact))).toEqual([]);
});
