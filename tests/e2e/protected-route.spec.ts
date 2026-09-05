import { expect, test } from './fixtures';

test.describe('Protected routes', () => {
    test('guest is redirected to login when accessing dashboard', async ({
        page,
    }) => {
        await page.goto('/dashboard');

        await expect(page).toHaveURL(/\/login/);
    });

    test('guest is redirected to login when accessing settings', async ({
        page,
    }) => {
        await page.goto('/settings');

        await expect(page).toHaveURL(/\/login/);
    });

    test('guest is redirected to login when accessing verify email', async ({
        page,
    }) => {
        await page.goto('/verify-email');

        await expect(page).toHaveURL(/\/login/);
    });
});
