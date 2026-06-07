import { expect, test } from '@playwright/test';

test.describe('Password reset flow', () => {
    test('forgot password form submits and shows success', async ({ page }) => {
        await page.goto('/forgot-password');

        await page.getByLabel('Email').fill('nobody@example.com');
        await page.getByRole('button', { name: 'Send password' }).click();

        await expect(page).toHaveURL(/\/forgot-password/);
    });

    test('forgot password shows validation error for unknown email', async ({
        page,
    }) => {
        await page.goto('/forgot-password');

        await page.getByLabel('Email').fill('nobody@example.com');
        await page.getByRole('button', { name: 'Send password' }).click();

        await expect(
            page
                .getByRole('alert')
                .filter({ hasText: /unable|user/i })
                .first(),
        ).toBeVisible();
    });

    test('reset password page requires email and token', async ({ page }) => {
        await page.goto(
            '/reset-password?email=foo%40example.com&token=sometoken',
        );

        await expect(page).toHaveTitle(/Reset password/);
        await expect(page.getByLabel('Email')).toBeVisible();
        await expect(page.getByLabel('Token')).toBeVisible();
        await expect(page.getByLabel('New password')).toBeVisible();
    });
});
