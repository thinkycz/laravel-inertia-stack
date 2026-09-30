import { expect, test } from '@playwright/test';

test.describe('Full user journey', () => {
    test('user can register, view dashboard, update profile, and log out', async ({
        page,
    }) => {
        const email = `e2e-${Date.now()}@example.com`;

        await page.goto('/register');
        await page.getByLabel('Email').fill(email);
        await page.getByLabel('Password', { exact: true }).fill('password1');
        await page.getByLabel('Confirm password').fill('password1');
        await page.getByLabel('Locale').selectOption('en');
        await page.getByRole('button', { name: 'Register' }).click();

        await page.waitForURL(/\/dashboard/);
        await expect(
            page.getByRole('heading', { name: 'Welcome to your workspace' }),
        ).toBeVisible();

        await page.goto('/settings');
        const emailInput = page.getByLabel('Email');
        await emailInput.fill(`e2e-updated-${Date.now()}@example.com`);
        await page.getByRole('button', { name: 'Save profile' }).click();

        await page.waitForURL(/\/settings$/);

        await page.getByRole('button', { name: 'Log out' }).click();
        await page.waitForURL(/\/login|\/$/);
    });

    test('login form shows error for unknown user', async ({ page }) => {
        await page.goto('/login');

        await page.getByLabel('Email').fill('unknown-e2e@example.com');
        await page.getByLabel('Password', { exact: true }).fill('password');
        await page.getByRole('button', { name: 'Log in' }).click();

        await expect(page.getByRole('alert').first()).toBeVisible();
    });
});
