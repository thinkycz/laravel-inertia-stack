import { expect, test } from './fixtures';

test.describe('Password reset flow', () => {
    test('forgot password form submits and shows success', async ({ page }) => {
        const email = `reset-${Date.now()}@example.com`;
        await page.goto('/register');
        await page.getByLabel('Email').fill(email);
        await page.getByLabel('Password', { exact: true }).fill('password1');
        await page.getByLabel('Confirm password').fill('password1');
        await page.getByLabel('Locale').selectOption('en');
        await page.getByRole('button', { name: 'Register' }).click();
        await page.waitForURL(/\/dashboard/);
        await page.getByRole('button', { name: 'Log out' }).click();
        await page.waitForURL(/\/login/);
        await page.goto('/forgot-password');
        await page.getByLabel('Email').fill(email);
        await page.getByRole('button', { name: 'Send reset link' }).click();
        await expect(
            page.getByRole('alert').filter({
                hasText:
                    'A password reset link has been sent to your email address.',
            }),
        ).toBeVisible();
    });

    test('forgot password shows validation error for unknown email', async ({
        page,
    }) => {
        await page.goto('/forgot-password');

        await page.getByLabel('Email').fill('nobody@example.com');
        await page.getByRole('button', { name: 'Send reset link' }).click();

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
