import { expect, test, type Page } from './fixtures';

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

    test('a real queued run completes through the fake provider', async ({
        page,
    }) => {
        await register(page);
        await page
            .getByPlaceholder('Type a message...')
            .fill('Hello test worker');
        await page.keyboard.press('Enter');

        await expect(
            page.getByText('Test assistant response: Hello test worker', {
                exact: true,
            }),
        ).toBeVisible();
    });

    test('provider failure exposes a safe error', async ({ page }) => {
        await register(page);
        await page.getByPlaceholder('Type a message...').fill('[e2e:failure]');
        await page.keyboard.press('Enter');

        await expect(
            page.getByText(
                'The assistant could not complete this response. Please try again.',
                {
                    exact: true,
                },
            ),
        ).toBeVisible();
        await expect(page.getByText(/Synthetic provider failure/)).toHaveCount(
            0,
        );
    });

    test('two turns stay in one canonical conversation without duplicate bubbles', async ({
        page,
    }) => {
        await register(page);
        const composer = page.getByPlaceholder('Type a message...');
        await composer.fill('First turn');
        await page.keyboard.press('Enter');
        await expect(page).toHaveURL(/\/conversations\/[^/]+$/);
        const conversationUrl = page.url();
        await expect(
            page.getByText('Test assistant response: First turn', {
                exact: true,
            }),
        ).toBeVisible();
        await expect(composer).toBeEnabled();
        await composer.fill('Second turn');
        await page.keyboard.press('Enter');
        await expect(
            page.getByText('Test assistant response: Second turn', {
                exact: true,
            }),
        ).toBeVisible();
        await expect(page).toHaveURL(conversationUrl);
        await page.reload();
        for (const content of [
            'First turn',
            'Second turn',
            'Test assistant response: First turn',
            'Test assistant response: Second turn',
        ]) {
            await expect(
                page.locator('main').getByText(content, { exact: true }),
            ).toHaveCount(1);
        }
    });

    test('refresh while a worker runs restores its single assistant message', async ({
        page,
    }) => {
        await register(page);
        await page
            .getByPlaceholder('Type a message...')
            .fill('[e2e:slow] Refresh me');
        await page.keyboard.press('Enter');
        await expect(page).toHaveURL(/\/conversations\/[^/]+$/);
        await page.reload();
        await expect(
            page.getByText('Test assistant response: [e2e:slow] Refresh me', {
                exact: true,
            }),
        ).toBeVisible({ timeout: 15000 });
        await expect(
            page.locator('main').getByText('Assistant', { exact: true }),
        ).toHaveCount(1);
        await expect(page.getByPlaceholder('Type a message...')).toBeEnabled();
    });

    test('composer supports Shift Enter and does not submit during composition', async ({
        page,
    }) => {
        await register(page);
        const composer = page.getByPlaceholder('Type a message...');
        await composer.fill('First line');
        await composer.press('Shift+Enter');
        await composer.press('End');
        await composer.press('a');
        await expect(composer).toHaveValue('First line\na');
        await composer.dispatchEvent('keydown', {
            key: 'Enter',
            isComposing: true,
        });
        await expect(page).toHaveURL(/\/dashboard$/);
        await expect(composer).toHaveValue('First line\na');
    });

    test('deletion dialog traps focus and Escape restores its trigger', async ({
        page,
    }) => {
        await register(page);
        await page
            .getByPlaceholder('Type a message...')
            .fill('Delete dialog test');
        await page.keyboard.press('Enter');
        await expect(
            page.getByText('Test assistant response: Delete dialog test', {
                exact: true,
            }),
        ).toBeVisible();
        const trigger = page
            .getByRole('button', { name: 'Delete Conversation' })
            .first();
        await trigger.focus();
        await trigger.press('Enter');
        const dialog = page.getByRole('dialog', { name: 'Are you sure?' });
        await expect(dialog).toBeVisible();
        await expect(
            dialog.getByRole('button', { name: 'Cancel', exact: true }),
        ).toBeFocused();
        await page.keyboard.press('Shift+Tab');
        await expect(
            dialog.getByRole('button', { name: 'Confirm', exact: true }),
        ).toBeFocused();
        await page.keyboard.press('Tab');
        await expect(
            dialog.getByRole('button', { name: 'Cancel', exact: true }),
        ).toBeFocused();
        await page.keyboard.press('Escape');
        await expect(dialog).toHaveCount(0);
        await expect(trigger).toBeFocused();
        await trigger.press('Enter');
        await dialog
            .getByRole('button', { name: 'Confirm', exact: true })
            .click();
        await expect(page).toHaveURL(/\/dashboard$/);
        await expect(
            page.getByRole('link', { name: /Delete dialog test/ }),
        ).toHaveCount(0);
    });

    test('a conflict response attaches to the existing run', async ({
        page,
    }) => {
        await register(page);
        await page.route('**/agent/runs', async (route) => {
            const response = await route.fetch();
            await route.fulfill({ response, status: 409 });
        });
        await page
            .getByPlaceholder('Type a message...')
            .fill('Attach existing run');
        await page.keyboard.press('Enter');
        await expect(
            page.getByText('Test assistant response: Attach existing run', {
                exact: true,
            }),
        ).toBeVisible();
        await expect(page.getByPlaceholder('Type a message...')).toBeEnabled();
    });

    test('cancel failure can be retried and confirmed cancellation permits another turn', async ({
        page,
    }) => {
        await register(page);
        const composer = page.getByPlaceholder('Type a message...');
        await composer.fill('[e2e:slow] Cancel me');
        await page.keyboard.press('Enter');
        await expect(page.getByText('Running…', { exact: true })).toBeVisible();
        await page.route('**/agent/runs/cancel', (route) =>
            route.fulfill({ status: 500, body: '{}' }),
        );
        await page
            .getByRole('button', { name: 'Cancel run', exact: true })
            .click();
        await page
            .getByRole('dialog')
            .getByRole('button', { name: 'Cancel run', exact: true })
            .click();
        await expect(
            page.getByText('Unable to cancel. Try again.', { exact: true }),
        ).toBeVisible();
        await page.unroute('**/agent/runs/cancel');
        await page.route('**/conversations/*', async (route) => {
            if (route.request().method() === 'GET') {
                await new Promise((resolve) => setTimeout(resolve, 500));
            }
            await route.continue();
        });
        const cancellationAcknowledged = page.waitForResponse(
            (response) =>
                response.url().endsWith('/agent/runs/cancel') &&
                response.status() === 200,
        );
        await page
            .getByRole('button', { name: 'Cancel run', exact: true })
            .click();
        await page
            .getByRole('dialog')
            .getByRole('button', { name: 'Cancel run', exact: true })
            .click();
        await cancellationAcknowledged;
        await expect(composer).toBeDisabled();
        await expect(composer).toBeEnabled();
        await composer.fill('After cancellation');
        await page.keyboard.press('Enter');
        await expect(
            page.getByText('Test assistant response: After cancellation', {
                exact: true,
            }),
        ).toBeVisible({ timeout: 15000 });
        await page.reload();
        await expect(
            page.getByText('Test assistant response: [e2e:slow] Cancel me', {
                exact: true,
            }),
        ).toHaveCount(0);
    });

    test('exhausted reconnection offers retry without completing the run locally', async ({
        page,
    }) => {
        test.setTimeout(45000);
        await register(page);
        await page.route('**/agent/runs/stream?*', (route) => route.abort());
        const composer = page.getByPlaceholder('Type a message...');
        await composer.fill('Reconnect me');
        await page.keyboard.press('Enter');
        const retry = page.getByRole('button', { name: 'Retry connection' });
        await expect(retry).toBeVisible({ timeout: 30000 });
        await expect(composer).toBeDisabled();
        await page.unroute('**/agent/runs/stream?*');
        await retry.click();
        await expect(
            page.getByText('Test assistant response: Reconnect me', {
                exact: true,
            }),
        ).toBeVisible();
        await expect(composer).toBeEnabled();
    });

    test('clarification selection starts a normal turn and stays selected after refresh', async ({
        page,
    }) => {
        await register(page);
        await page.getByPlaceholder('Type a message...').fill('[e2e:clarify]');
        await page.keyboard.press('Enter');
        await expect(
            page.getByText('Which language should I use?', { exact: true }),
        ).toBeVisible();
        const choice = page.getByRole('button', { name: /English/ });
        await expect(choice).toBeEnabled();
        await choice.click();
        await expect(
            page.getByText('Test assistant response: English', { exact: true }),
        ).toBeVisible();
        await page.reload();
        await expect(
            page.getByRole('button', { name: /English/ }),
        ).toBeDisabled();
        await expect(
            page.locator('main').getByText('English', { exact: true }),
        ).toHaveCount(2);
    });

    test('navigating away from a running conversation rejects its obsolete stream', async ({
        page,
    }) => {
        await register(page);
        const composer = page.getByPlaceholder('Type a message...');
        await composer.fill('First conversation');
        await page.keyboard.press('Enter');
        await expect(
            page.getByText('Test assistant response: First conversation', {
                exact: true,
            }),
        ).toBeVisible();
        const firstUrl = page.url();
        await page.getByRole('link', { name: 'New Chat', exact: true }).click();
        await expect(page).toHaveURL(/\/dashboard$/);
        await expect(
            page.getByText('How can I help you today?', { exact: true }),
        ).toBeVisible();
        await composer.fill('[e2e:slow] Second conversation');
        await page.keyboard.press('Enter');
        await expect(page).toHaveURL(/\/conversations\/[^/]+$/);
        const secondUrl = page.url();
        expect(secondUrl).not.toBe(firstUrl);
        await page
            .getByRole('link', { name: 'First conversation', exact: true })
            .click();
        await expect(page).toHaveURL(firstUrl);
        await expect(
            page.getByText('Test assistant response: First conversation', {
                exact: true,
            }),
        ).toBeVisible();
        await expect(composer).toBeEnabled();
        await page.goto(secondUrl);
        await expect(
            page.getByText(
                'Test assistant response: [e2e:slow] Second conversation',
                { exact: true },
            ),
        ).toBeVisible({ timeout: 15000 });
        await expect(
            page
                .locator('main')
                .getByText('First conversation', { exact: true }),
        ).toHaveCount(0);
    });

    test('deleting a running conversation cannot resurrect it after the worker finishes', async ({
        page,
    }) => {
        await register(page);
        await page
            .getByPlaceholder('Type a message...')
            .fill('[e2e:slow] Delete while running');
        await page.keyboard.press('Enter');
        await expect(page).toHaveURL(/\/conversations\/[^/]+$/);
        const deletedUrl = page.url();
        await expect(page.getByText('Running…', { exact: true })).toBeVisible();
        await page
            .getByRole('button', { name: 'Delete Conversation' })
            .first()
            .click();
        await page
            .getByRole('dialog')
            .getByRole('button', { name: 'Confirm', exact: true })
            .click();
        await expect(page).toHaveURL(/\/dashboard$/);
        await page.getByPlaceholder('Type a message...').fill('After deletion');
        await page.keyboard.press('Enter');
        await expect(
            page.getByText('Test assistant response: After deletion', {
                exact: true,
            }),
        ).toBeVisible({ timeout: 15000 });
        const response = await page.request.get(deletedUrl, {
            headers: { Accept: 'application/json' },
            maxRedirects: 0,
        });
        expect(response.status()).toBe(302);
        expect(response.headers().location).toMatch(/\/dashboard$/);
        await page.reload();
        await expect(
            page.getByRole('link', { name: /Delete while running/ }),
        ).toHaveCount(0);
    });

    test('a clarification also accepts an ordinary typed next-turn answer', async ({
        page,
    }) => {
        await register(page);
        const composer = page.getByPlaceholder('Type a message...');
        await composer.fill('[e2e:clarify]');
        await page.keyboard.press('Enter');
        await expect(
            page.getByText('Which language should I use?', { exact: true }),
        ).toBeVisible();
        await expect(composer).toBeEnabled();
        const conversationUrl = page.url();
        await composer.fill('Please use Czech and keep it brief.');
        await page.keyboard.press('Enter');
        await expect(
            page.getByText(
                'Test assistant response: Please use Czech and keep it brief.',
                { exact: true },
            ),
        ).toBeVisible();
        await expect(page).toHaveURL(conversationUrl);
    });
});
