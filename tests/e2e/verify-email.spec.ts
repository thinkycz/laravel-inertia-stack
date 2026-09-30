import { expect, test } from '@playwright/test';

test.describe('Email verification', () => {
    test('send verification email shows success flash', async ({ page }) => {
        const email = `verify-${Date.now()}@example.com`;

        await page.goto('/register');
        await page.getByLabel('Email', { exact: true }).fill(email);
        await page.getByLabel('Password', { exact: true }).fill('password1');
        await page.getByLabel('Confirm password').fill('password1');
        await page.getByLabel('Locale').selectOption('en');
        await page.getByRole('button', { name: 'Register' }).click();
        await page.waitForURL(/\/dashboard/);

        await page.goto('/verify-email');
        await page
            .getByRole('button', { name: 'Send verification email' })
            .click();

        await expect(
            page
                .getByRole('alert')
                .filter({ hasText: 'Verification email sent.' })
                .first(),
        ).toBeVisible();
    });

    test('verify-email page is reachable while logged in', async ({ page }) => {
        const email = `verify-${Date.now()}@example.com`;

        await page.goto('/register');
        await page.getByLabel('Email', { exact: true }).fill(email);
        await page.getByLabel('Password', { exact: true }).fill('password1');
        await page.getByLabel('Confirm password').fill('password1');
        await page.getByLabel('Locale').selectOption('en');
        await page.getByRole('button', { name: 'Register' }).click();
        await page.waitForURL(/\/dashboard/);

        await page.goto('/verify-email');
        await expect(
            page.getByRole('heading', { name: 'Verify email' }),
        ).toBeVisible();
    });
});
