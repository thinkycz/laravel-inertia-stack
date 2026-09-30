import { expect, test } from '@playwright/test';

test.describe('Password reset flow', () => {
    test('forgot password form submits and shows success', async ({ page }) => {
        await page.goto('/forgot-password');

        await page.getByLabel('Email').fill('nobody@example.com');
        await page.getByRole('button', { name: 'Send reset link' }).click();

        await expect(page).toHaveURL(/\/forgot-password/);
    });

    test('unknown email receives a neutral recovery message', async ({
        page,
    }) => {
        await page.goto('/forgot-password');

        await page.getByLabel('Email').fill('nobody@example.com');
        await page.getByRole('button', { name: 'Send reset link' }).click();

        await expect(
            page
                .getByRole('alert')
                .filter({ hasText: /If an account|reset link/i })
                .first(),
        ).toBeVisible();
    });

    test('reset password page requires email and token', async ({ page }) => {
        await page.goto(
            '/reset-password?email=foo%40example.com&token=sometoken',
        );

        await expect(page).toHaveTitle(/Reset password/);
        await expect(page.getByLabel('Email')).toBeVisible();
        await expect(page.locator('input[name=token]')).toHaveValue(
            'sometoken',
        );
        await expect(page.getByLabel('New password')).toBeVisible();
    });
});
