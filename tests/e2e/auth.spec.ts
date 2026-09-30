import { expect, test } from '@playwright/test';

test.describe('Auth flow', () => {
    test('guest can view the login page', async ({ page }) => {
        await page.goto('/login');

        await expect(page).toHaveTitle(/Log in/);
        await expect(page.getByLabel('Email')).toBeVisible();
        await expect(
            page.getByLabel('Password', { exact: true }),
        ).toBeVisible();
        await expect(
            page.getByRole('button', { name: 'Log in' }),
        ).toBeVisible();
    });

    test('guest can view the register page', async ({ page }) => {
        await page.goto('/register');

        await expect(page).toHaveTitle(/Create account/);
        await expect(page.getByLabel('Email')).toBeVisible();
        await expect(
            page.getByLabel('Password', { exact: true }),
        ).toBeVisible();
        await expect(page.getByLabel('Confirm password')).toBeVisible();
        await expect(page.getByLabel('Locale')).toBeVisible();
        await expect(
            page.getByRole('button', { name: 'Register' }),
        ).toBeVisible();
    });

    test('login form has links to forgot password and register', async ({
        page,
    }) => {
        await page.goto('/login');

        await expect(
            page.getByRole('link', { name: 'Forgot password?' }),
        ).toBeVisible();
        await expect(
            page.getByRole('link', { name: 'Create account' }),
        ).toBeVisible();
    });

    test('forgot password page is reachable', async ({ page }) => {
        await page.goto('/forgot-password');

        await expect(page).toHaveTitle(/Forgot password/);
        await expect(page.getByLabel('Email')).toBeVisible();
        await expect(
            page.getByRole('button', { name: 'Send reset link' }),
        ).toBeVisible();
    });
});
