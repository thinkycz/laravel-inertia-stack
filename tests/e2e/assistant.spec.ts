import { expect, test, type Page } from '@playwright/test';

async function register(page: Page) {
    const email = `assistant-${Date.now()}-${Math.random().toString(36).slice(2)}@example.com`;

    await page.goto('/register');
    await page.getByLabel('Email').fill(email);
    await page.getByLabel('Password', { exact: true }).fill('password1');
    await page.getByLabel('Confirm password').fill('password1');
    await page.getByLabel('Locale').selectOption('en');
    await page.getByRole('button', { name: 'Register' }).click();
    await page.waitForURL(/\/dashboard/);
}

test.describe('Assistant chat UI', () => {
    test('mobile users can open conversation history', async ({ page }) => {
        await page.setViewportSize({ width: 390, height: 844 });
        await register(page);

        await page.getByRole('button', { name: 'Conversations' }).click();

        await expect(page.getByText('No conversations yet')).toBeVisible();
    });

    test('failed first stream shows the error and removes optimistic history', async ({
        page,
    }) => {
        await register(page);

        await page.route('**/conversations', async (route) => {
            if (route.request().method() !== 'POST') {
                await route.fallback();

                return;
            }

            await route.fulfill({
                status: 200,
                headers: {
                    'Content-Type': 'text/event-stream',
                    'X-Conversation-ID': 'e2e-failed-conversation',
                },
                body: 'data: {"type":"error","message":"Failed to generate response. Please try again."}\n\n',
            });
        });

        await page
            .getByPlaceholder('Type a message...')
            .fill('Hello failing AI');
        await page.keyboard.press('Enter');

        await expect(
            page.getByText('Failed to generate response. Please try again.'),
        ).toBeVisible();
        await expect(
            page.getByRole('link', { name: /Hello failing AI/ }),
        ).toHaveCount(0);
    });
});
