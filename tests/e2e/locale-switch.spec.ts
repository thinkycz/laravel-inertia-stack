import { expect, test } from './fixtures';

test.describe('Locale switcher', () => {
    test.beforeEach(async ({ page }) => {
        const email = `locale-${Date.now()}-${Math.random().toString(36).slice(2)}@example.com`;
        await page.goto('/register');
        await page.getByLabel('Email', { exact: true }).fill(email);
        await page.getByLabel('Password', { exact: true }).fill('password1');
        await page.getByLabel('Confirm password').fill('password1');
        await page.getByLabel('Locale').selectOption('en');
        await page.getByRole('button', { name: 'Register' }).click();
        await page.waitForURL(/\/dashboard/);
    });

    test('switching the locale flips the nav and heading strings', async ({
        page,
    }) => {
        await expect(page).toHaveTitle(/Dashboard/);
        await expect(
            page.getByRole('button', { name: 'Log out' }),
        ).toBeVisible();

        await page.goto('/settings');

        const switcher = page.locator('select#locale');
        await switcher.selectOption('cs');
        await page.getByRole('button', { name: 'Save profile' }).click();

        await expect(
            page.getByRole('heading', { name: 'Nastavení' }),
        ).toBeVisible();
        await expect(
            page.getByRole('button', { name: 'Odhlásit se' }),
        ).toBeVisible();

        await switcher.selectOption('sk');
        await page.getByRole('button', { name: 'Uložit profil' }).click();

        await expect(
            page.getByRole('heading', { name: 'Nastavenia' }),
        ).toBeVisible();
        await expect(
            page.getByRole('button', { name: 'Odhlásiť sa' }),
        ).toBeVisible();

        await switcher.selectOption('en');
        await page.getByRole('button', { name: 'Uložiť profil' }).click();

        await expect(
            page.getByRole('heading', { name: 'Settings' }),
        ).toBeVisible();
        await expect(
            page.getByRole('button', { name: 'Log out' }),
        ).toBeVisible();
    });

    test('navigating to settings shows the localized page title', async ({
        page,
    }) => {
        await page.goto('/settings');

        const switcher = page.locator('select#locale');
        await switcher.selectOption('cs');
        await page.getByRole('button', { name: 'Save profile' }).click();

        await page.getByRole('link', { name: 'Nástěnka', exact: true }).click();
        await page.waitForURL(/\/dashboard$/);
        await page
            .getByRole('link', { name: 'Nastavení', exact: true })
            .click();
        await page.waitForURL(/\/settings$/);

        await expect(
            page.getByRole('heading', { name: 'Nastavení' }),
        ).toBeVisible();
    });
});
