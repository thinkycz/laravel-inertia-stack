import { expect, test } from '@playwright/test';

test('an enabled assistant explains missing credentials and rejects sending', async ({
    page,
}) => {
    await page.goto('/register');
    await page
        .getByLabel('Email')
        .fill(`unconfigured-${Date.now()}@example.com`);
    await page.getByLabel('Password', { exact: true }).fill('password1');
    await page.getByLabel('Confirm password').fill('password1');
    await page.getByLabel('Locale').selectOption('en');
    await page.getByRole('button', { name: 'Register' }).click();
    await expect(page).toHaveURL(/\/dashboard$/);
    await page.getByRole('link', { name: 'Assistant', exact: true }).click();
    await expect(page.getByRole('status')).toContainText('not available');
    await expect(page.getByPlaceholder('Type a message...')).toBeDisabled();
    await expect(
        page.getByRole('button', { name: 'Send message' }),
    ).toBeDisabled();
    await page.reload();
    const csrf = await page
        .locator('meta[name="csrf-token"]')
        .getAttribute('content');
    const response = await page.request.post('/agent/chat', {
        data: { message: 'Hello' },
        headers: { 'X-CSRF-TOKEN': csrf ?? '', Accept: 'application/json' },
    });
    expect(response.status()).toBe(503);
});
