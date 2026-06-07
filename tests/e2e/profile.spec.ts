import { expect, test } from '@playwright/test';
import { faker } from '@faker-js/faker';

test.describe('Profile management', () => {
    test.beforeEach(async ({ page }) => {
        const email = faker.internet.email();
        await page.goto('/register');
        await page.getByLabel('Email').fill(email);
        await page.getByLabel('Password').fill('password1');
        await page.getByLabel('Locale').selectOption('en');
        await page.getByRole('button', { name: 'Register' }).click();
        await page.waitForURL(/\/dashboard/);
    });

    test('user can change locale and see success flash', async ({ page }) => {
        await page.goto('/settings/profile');

        await page.getByLabel('Locale').selectOption('cs');
        await page.getByRole('button', { name: 'Save profile' }).click();

        await expect(page).toHaveURL(/\/settings\/profile$/);
        await expect(
            page.getByRole('alert').filter({ hasText: 'Profile updated.' }),
        ).toBeVisible();
    });

    test('user can navigate to password settings', async ({ page }) => {
        await page.goto('/settings/profile');

        await page.getByRole('link', { name: 'Change password' }).click();
        await expect(page).toHaveURL(/\/settings\/password$/);
    });
});
