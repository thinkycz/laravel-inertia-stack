import { expect, test } from './fixtures';

test.describe('Profile management', () => {
    test.beforeEach(async ({ page }) => {
        const email = `profile-${Date.now()}-${Math.random().toString(36).slice(2)}@example.com`;
        await page.goto('/register');
        await page.getByLabel('Email').fill(email);
        await page.getByLabel('Password', { exact: true }).fill('password1');
        await page.getByLabel('Confirm password').fill('password1');
        await page.getByLabel('Locale').selectOption('en');
        await page.getByRole('button', { name: 'Register' }).click();
        await page.waitForURL(/\/dashboard/);
    });

    test('user can change locale and see success flash', async ({ page }) => {
        await page.goto('/settings');

        await expect(
            page.getByRole('heading', { name: 'Password' }),
        ).toBeVisible();

        await page.getByLabel('Locale').selectOption('cs');
        await page.getByRole('button', { name: 'Save profile' }).click();

        await expect(page).toHaveURL(/\/settings$/);
        await expect(
            page.getByRole('alert').filter({ hasText: 'Profile updated.' }),
        ).toBeVisible();
    });
});
