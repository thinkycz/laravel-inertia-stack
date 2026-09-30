import { expect, test, type Page } from '@playwright/test';
import { readFileSync, mkdirSync } from 'node:fs';
import { execFileSync } from 'node:child_process';

const password = 'test-password-123';
async function register(page: Page): Promise<string> {
    const email = `account-${Date.now()}-${Math.random().toString(36).slice(2)}@example.com`;
    await page.goto('/register');
    await page.getByLabel('Email', { exact: true }).fill(email);
    await page.getByLabel('Password', { exact: true }).fill(password);
    await page.getByLabel('Confirm password').fill(password);
    await page.getByLabel('Locale').selectOption('en');
    await page.getByRole('button', { name: 'Register', exact: true }).click();
    await expect(page).toHaveURL(/\/dashboard$/);
    return email;
}

function fixturePaths(): { database: string; mail: string } {
    return JSON.parse(readFileSync('storage/framework/e2e/8173.json', 'utf8'));
}

async function mailLink(email: string, path: string): Promise<string> {
    let url = '';
    await expect
        .poll(() => {
            const mail = readFileSync(fixturePaths().mail, 'utf8');
            const blocks = mail
                .split(/\n\[[\d-]+ /)
                .filter((block) => block.includes(email));
            const links = blocks.flatMap(
                (block) =>
                    block
                        .replace(/=\r?\n/g, '')
                        .replaceAll('=3D', '=')
                        .match(/http:\/\/127\.0\.0\.1:8173[^\s<>"\)]+/g) ?? [],
            );
            url =
                links
                    .filter((value) => value.includes(path))
                    .at(-1)
                    ?.replaceAll('&amp;', '&') ?? '';
            return url;
        })
        .not.toBe('');
    return url;
}

test('AI is disabled by default and unavailable routes return 404', async ({
    page,
}) => {
    await register(page);
    await expect(
        page.getByRole('link', { name: 'Assistant', exact: true }),
    ).toHaveCount(0);
    const response = await page.goto('/assistant');
    expect(response?.status()).toBe(404);
});

test('verification and email changes use captured mail links', async ({
    page,
}) => {
    const email = await register(page);
    await page.goto(await mailLink(email, '/email/verify'));
    await page.goto('/dashboard');
    await expect(page.getByText('Verified', { exact: true })).toBeVisible();
    await page.goto('/settings');
    const updated = `changed-${email}`;
    await page.getByLabel('Email', { exact: true }).fill(updated);
    await page.getByRole('button', { name: 'Save profile' }).click();
    await page.goto('/dashboard');
    await expect(page.getByText('Awaiting verification')).toBeVisible();
    await page.goto(await mailLink(updated, '/email/verify'));
    await page.goto('/dashboard');
    await expect(page.getByText('Verified', { exact: true })).toBeVisible();
});

test('reset links change the password and revoke previous sessions', async ({
    page,
    browser,
}) => {
    const email = await register(page);
    const recoveryContext = await browser.newContext();
    const recovery = await recoveryContext.newPage();
    await recovery.goto('http://127.0.0.1:8173/forgot-password');
    await recovery.getByLabel('Email').fill(email);
    await recovery.getByRole('button', { name: 'Send reset link' }).click();
    await expect(recovery.getByRole('alert')).toContainText('reset link');
    await recovery.goto(await mailLink(email, '/reset-password'));
    await recovery
        .getByLabel('New password', { exact: true })
        .fill('new-test-password-123');
    await recovery.getByRole('button', { name: 'Update password' }).click();
    await expect(recovery).toHaveURL(/\/dashboard$/);
    await page.goto('/dashboard');
    await expect(page).toHaveURL(/\/login$/);
    await recoveryContext.close();
});

test('revoking another session confirms the password and signs that device out', async ({
    page,
    browser,
}) => {
    const email = await register(page);
    const secondContext = await browser.newContext();
    const second = await secondContext.newPage();
    await second.goto('http://127.0.0.1:8173/login');
    await second.getByLabel('Email').fill(email);
    await second.getByLabel('Password', { exact: true }).fill(password);
    await second.getByRole('button', { name: 'Log in' }).click();
    await expect(second).toHaveURL(/\/dashboard$/);
    await page.goto('/settings');
    await expect(page.getByText('This device', { exact: true })).toBeVisible();
    await page
        .getByRole('button', { name: 'Revoke session', exact: true })
        .click();
    const dialog = page.getByRole('dialog', { name: 'Revoke session' });
    await dialog.getByLabel('Current password').fill('incorrect-password');
    await dialog
        .getByRole('button', { name: 'Revoke session', exact: true })
        .click();
    await expect(dialog.locator('[id=revoke-password-error]')).toBeVisible();
    await dialog.getByLabel('Current password').fill(password);
    await dialog
        .getByRole('button', { name: 'Revoke session', exact: true })
        .click();
    await expect(dialog).toHaveCount(0);
    await second.goto('http://127.0.0.1:8173/dashboard');
    await expect(second).toHaveURL(/\/login$/);
    await secondContext.close();
});

test('an expired browser token requires sign-in', async ({ page }) => {
    const email = await register(page);
    execFileSync('php', [
        '-r',
        '$db = new PDO("sqlite:".$argv[1]); $query = $db->prepare("UPDATE database_tokens SET expires_at = ? WHERE user_id = (SELECT id FROM users WHERE email = ?)"); $query->execute(["2000-01-01 00:00:00", $argv[2]]);',
        fixturePaths().database,
        email,
    ]);
    await page.goto('/dashboard');
    await expect(page).toHaveURL(/\/login$/);
});

test('a user cannot revoke a session owned by someone else', async ({
    page,
    browser,
}) => {
    await register(page);
    const otherContext = await browser.newContext({
        baseURL: 'http://127.0.0.1:8173',
    });
    const other = await otherContext.newPage();
    const otherEmail = await register(other);
    const id = execFileSync('php', [
        '-r',
        '$db = new PDO("sqlite:".$argv[1]); $query = $db->prepare("SELECT id FROM database_tokens WHERE user_id = (SELECT id FROM users WHERE email = ?)"); $query->execute([$argv[2]]); echo $query->fetchColumn();',
        fixturePaths().database,
        otherEmail,
    ])
        .toString()
        .trim();
    await page.goto('/settings');
    const csrf = await page
        .locator('meta[name="csrf-token"]')
        .getAttribute('content');
    const response = await page.request.post(
        `/settings/sessions/${id}/revoke`,
        {
            data: { password },
            headers: { 'X-CSRF-TOKEN': csrf ?? '', Accept: 'application/json' },
        },
    );
    expect(response.status()).toBe(404);
    await other.goto('/dashboard');
    await expect(other).toHaveURL(/\/dashboard$/);
    await otherContext.close();
});

test('changing a password signs out every device with an explicit result', async ({
    page,
}) => {
    const email = await register(page);
    await page.goto('/settings');
    await page.getByLabel('Current password', { exact: true }).fill(password);
    await page
        .getByLabel('New password', { exact: true })
        .fill('changed-password-123');
    await page
        .getByRole('button', { name: 'Update password', exact: true })
        .click();
    await expect(page).toHaveURL(/\/login$/);
    await expect(page.getByRole('alert')).toContainText('sign in again');
    await page.getByLabel('Email').fill(email);
    await page
        .getByLabel('Password', { exact: true })
        .fill('changed-password-123');
    await page.getByRole('button', { name: 'Log in' }).click();
    await expect(page).toHaveURL(/\/dashboard$/);
});

test('mobile navigation works with a keyboard and restores focus', async ({
    page,
}) => {
    await page.setViewportSize({ width: 390, height: 844 });
    await register(page);
    const trigger = page.getByRole('button', { name: 'Open navigation' });
    await trigger.focus();
    await page.keyboard.press('Enter');
    const dialog = page.getByRole('dialog', { name: 'Navigation' });
    await expect(dialog.getByRole('link', { name: 'Overview' })).toBeFocused();
    await page.keyboard.press('Tab');
    await expect(dialog.getByRole('link', { name: 'Settings' })).toBeFocused();
    await page.keyboard.press('Escape');
    await expect(trigger).toBeFocused();
    await page.keyboard.press('Enter');
    await page.keyboard.press('Tab');
    await page.keyboard.press('Enter');
    await expect(page).toHaveURL(/\/settings$/);
    await expect(dialog).toHaveCount(0);
});

test('deleting an account validates the password and ends authentication', async ({
    page,
}) => {
    const email = await register(page);
    await page.goto('/settings');
    await page
        .getByRole('button', { name: 'Delete account', exact: true })
        .click();
    const dialog = page.getByRole('dialog', { name: 'Delete account' });
    await dialog.getByLabel('Current password').fill('incorrect-password');
    await dialog
        .getByRole('button', { name: 'Delete account', exact: true })
        .click();
    await expect(dialog.locator('[id=delete-password-error]')).toBeVisible();
    await dialog.getByLabel('Current password').fill(password);
    await dialog
        .getByRole('button', { name: 'Delete account', exact: true })
        .click();
    await expect(page).toHaveURL(/\/login$/);
    await expect(page.getByRole('alert')).toContainText('deleted');
    await page.getByLabel('Email').fill(email);
    await page.getByLabel('Password', { exact: true }).fill(password);
    await page.getByRole('button', { name: 'Log in' }).click();
    await expect(page.getByRole('alert').last()).toBeVisible();
    await expect(page).toHaveURL(/\/login$/);
});

test('appearance persists and dialogs trap and restore keyboard focus', async ({
    page,
}) => {
    await register(page);
    await page.goto('/settings');
    await page.getByRole('button', { name: 'Dark', exact: true }).click();
    await page.reload();
    await expect(page.locator('html')).toHaveClass(/dark/);
    await expect(
        page.getByRole('button', { name: 'Dark', exact: true }),
    ).toHaveAttribute('aria-pressed', 'true');
    const trigger = page.getByRole('button', {
        name: 'Delete account',
        exact: true,
    });
    await trigger.click();
    const dialog = page.getByRole('dialog', { name: 'Delete account' });
    await expect(dialog.getByLabel('Current password')).toBeFocused();
    await page.keyboard.press('Shift+Tab');
    await expect(
        dialog.getByRole('button', { name: 'Delete account' }),
    ).toBeFocused();
    await page.keyboard.press('Tab');
    await expect(dialog.getByLabel('Current password')).toBeFocused();
    await page.keyboard.press('Escape');
    await expect(dialog).toHaveCount(0);
    await expect(trigger).toBeFocused();
    await page.getByRole('button', { name: 'System', exact: true }).click();
    await page.emulateMedia({ colorScheme: 'light', reducedMotion: 'reduce' });
    await expect(page.locator('html')).not.toHaveClass(/dark/);
    await page.emulateMedia({ colorScheme: 'dark' });
    await expect(page.locator('html')).toHaveClass(/dark/);
});

test('overview and settings remain usable in all locales, themes, and viewport sizes', async ({
    page,
}) => {
    await register(page);
    mkdirSync('storage/app/verification', { recursive: true });
    for (const locale of ['en', 'cs', 'sk']) {
        await page.goto('/settings');
        await page.locator('select#locale').selectOption(locale);
        await page
            .locator('form')
            .filter({ has: page.locator('input#email') })
            .getByRole('button')
            .click();
        for (const dark of [false, true]) {
            const themeLabel = {
                en: ['Light', 'Dark'],
                cs: ['Světlý', 'Tmavý'],
                sk: ['Svetlý', 'Tmavý'],
            }[locale]!;
            await page
                .getByRole('button', {
                    name: themeLabel[dark ? 1 : 0],
                    exact: true,
                })
                .click();
            for (const mobile of [false, true]) {
                await page.setViewportSize({
                    width: mobile ? 390 : 1440,
                    height: mobile ? 844 : 1000,
                });
                await page.goto('/dashboard');
                await expect(page.locator('h1')).toBeVisible();
                expect(
                    await page.evaluate(
                        () =>
                            document.documentElement.scrollWidth <= innerWidth,
                    ),
                ).toBe(true);
                await page.screenshot({
                    path: `storage/app/verification/overview-${locale}-${dark ? 'dark' : 'light'}-${mobile ? 'mobile' : 'desktop'}.png`,
                    fullPage: true,
                });
                await page.goto('/settings');
                expect(
                    await page.evaluate(
                        () =>
                            document.documentElement.scrollWidth <= innerWidth,
                    ),
                ).toBe(true);
                await page.screenshot({
                    path: `storage/app/verification/settings-${locale}-${dark ? 'dark' : 'light'}-${mobile ? 'mobile' : 'desktop'}.png`,
                    fullPage: true,
                });
            }
            await page.goto('/settings');
        }
    }
});
