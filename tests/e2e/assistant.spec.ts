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

async function send(page: Page, message: string) {
    const composer = page.getByPlaceholder('Type a message...');
    await expect(composer).toBeEnabled();
    await composer.fill(message);
    await composer.press('Enter');
}

async function requestApproval(page: Page) {
    await send(page, '[e2e:approval]');
    await expect(
        page.getByRole('button', { name: 'Approve and send', exact: true }),
    ).toBeEnabled();
    await expect(page).toHaveURL(/\/conversations\/[^/]+$/);
}

test.describe('Assistant SDK chat and approvals', () => {
    test('mobile users can open conversation history', async ({ page }) => {
        await page.setViewportSize({ width: 390, height: 844 });
        await register(page);
        await page.getByRole('button', { name: 'Conversations' }).click();
        await expect(page.getByText('No conversations yet')).toBeVisible();
    });

    test('two turns share a canonical conversation and persist exactly one pair each', async ({
        page,
    }) => {
        await register(page);
        await send(page, 'First turn');
        await expect(
            page.getByText('Test assistant response: First turn', {
                exact: true,
            }),
        ).toBeVisible();
        await expect(page).toHaveURL(/\/conversations\/[^/]+$/);
        const url = page.url();
        await send(page, 'Second turn');
        await expect(
            page.getByText('Test assistant response: Second turn', {
                exact: true,
            }),
        ).toBeVisible();
        await expect(page).toHaveURL(url);
        await page.reload();
        for (const text of [
            'First turn',
            'Second turn',
            'Test assistant response: First turn',
            'Test assistant response: Second turn',
        ]) {
            await expect(
                page.locator('main').getByText(text, { exact: true }),
            ).toHaveCount(1);
        }
    });

    test('native pending approval survives refresh and approving resumes the same turn', async ({
        page,
    }) => {
        await register(page);
        await requestApproval(page);
        const url = page.url();
        await page.reload();
        await expect(page.getByLabel('Subject', { exact: true })).toHaveValue(
            'Original subject',
        );
        await page
            .getByRole('button', { name: 'Approve and send', exact: true })
            .click();
        await expect(
            page.getByText('Test assistant resumed after decision.', {
                exact: true,
            }),
        ).toBeVisible();
        await expect(page).toHaveURL(url);
        await page.reload();
        await expect(
            page.getByRole('button', { name: 'Approve and send', exact: true }),
        ).toHaveCount(0);
        await expect(
            page.locator('main').getByText('[e2e:approval]', { exact: true }),
        ).toHaveCount(1);
    });

    test('edited approval arguments are submitted once and duplicate replay is rejected', async ({
        page,
    }) => {
        await register(page);
        await requestApproval(page);
        await page
            .getByLabel('Subject', { exact: true })
            .fill('Edited subject');
        await page
            .getByLabel('Message', { exact: true })
            .fill('Edited notification body');
        const approvalRequest = page.waitForRequest(
            (request) =>
                request.url().endsWith('/agent/chat') &&
                request.postDataJSON()?.decisions,
        );
        let decisionRequests = 0;
        page.on('request', (request) => {
            if (
                request.url().endsWith('/agent/chat') &&
                request.postDataJSON()?.decisions
            )
                decisionRequests++;
        });
        await page
            .getByRole('button', { name: 'Approve and send', exact: true })
            .evaluate((button) => {
                (button as HTMLButtonElement).click();
                (button as HTMLButtonElement).click();
            });
        const request = await approvalRequest;
        const body = request.postDataJSON();
        expect(body.decisions['test-notification-call'].arguments).toEqual({
            subject: 'Edited subject',
            message: 'Edited notification body',
        });
        await expect(
            page.getByText('Test assistant resumed after decision.', {
                exact: true,
            }),
        ).toBeVisible();
        expect(decisionRequests).toBe(1);
        const response = await page.request.post('/agent/chat', {
            data: body,
            headers: {
                'X-XSRF-TOKEN': request.headers()['x-xsrf-token'],
                Accept: 'application/json',
            },
        });
        expect(response.status()).toBe(409);
        await page.reload();
        await expect(
            page.getByRole('button', { name: 'Approve and send', exact: true }),
        ).toHaveCount(0);
    });

    test('rejecting an approval persists rejection and permits a later normal prompt', async ({
        page,
    }) => {
        await register(page);
        await requestApproval(page);
        await page
            .getByLabel('Reason for rejection (optional)', { exact: true })
            .fill('Do not send this notification.');
        await page.getByRole('button', { name: 'Reject', exact: true }).click();
        await expect(
            page.getByText('Test assistant resumed after decision.', {
                exact: true,
            }),
        ).toBeVisible();
        await page.reload();
        await expect(
            page.getByRole('button', { name: 'Approve and send', exact: true }),
        ).toHaveCount(0);
        await expect(
            page.getByText('Test notification — Rejected', { exact: true }),
        ).toBeVisible();
        await send(page, 'Continue without sending');
        await expect(
            page.getByText(
                'Test assistant response: Continue without sending',
                { exact: true },
            ),
        ).toBeVisible();
    });

    test('provider failure exposes a safe message without exception details', async ({
        page,
    }) => {
        await register(page);
        await send(page, '[e2e:failure]');
        await expect(
            page.getByText(
                'The assistant could not complete this response. Please try again.',
                { exact: true },
            ),
        ).toBeVisible();
        await expect(page.getByText(/Synthetic provider failure/)).toHaveCount(
            0,
        );
    });

    test('stopping a stream refreshes saved state and handles a still-busy server before retry', async ({
        page,
    }) => {
        await register(page);
        await send(page, '[e2e:slow] Stop this response');
        await expect(page).toHaveURL(/\/conversations\/[^/]+$/);
        await page
            .getByRole('button', { name: 'Stop response', exact: true })
            .click();
        await expect(page.getByPlaceholder('Type a message...')).toBeDisabled();
        let accepted = false;
        for (let attempt = 0; attempt < 6; attempt++) {
            await page
                .getByRole('button', {
                    name: 'Refresh conversation',
                    exact: true,
                })
                .click();
            const pending = page.waitForResponse((response) =>
                response.url().endsWith('/agent/chat'),
            );
            await send(page, 'After refresh');
            const response = await pending;
            if (response.status() === 200) {
                accepted = true;
                break;
            }
            expect(response.status()).toBe(409);
            await expect(
                page.getByText(
                    'Another response is still being processed. Refresh before continuing.',
                    { exact: true },
                ),
            ).toBeVisible();
            await page.waitForTimeout(750);
        }
        expect(accepted).toBe(true);
        await expect(
            page.getByText('Test assistant response: After refresh', {
                exact: true,
            }),
        ).toBeVisible();
    });

    test('stopping before response headers allows a fresh conversation after refresh', async ({
        page,
    }) => {
        await register(page);
        await page.route('**/agent/chat', async (route) => {
            await new Promise((resolve) => setTimeout(resolve, 750));
            await route.abort('aborted');
        });
        await send(page, 'Stop before headers');
        await page
            .getByRole('button', { name: 'Stop response', exact: true })
            .click();
        await expect(page).toHaveURL(/\/dashboard$/);
        await page
            .getByRole('button', { name: 'Refresh conversation', exact: true })
            .click();
        await expect(page.getByPlaceholder('Type a message...')).toBeEnabled();
        await page.unroute('**/agent/chat');
        await send(page, 'Fresh after early stop');
        await expect(
            page.getByText('Test assistant response: Fresh after early stop', {
                exact: true,
            }),
        ).toBeVisible();
    });

    test('navigation aborts the old subscription without contaminating the selected chat', async ({
        page,
    }) => {
        await register(page);
        await send(page, 'First conversation');
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
        await send(page, '[e2e:slow] Other conversation');
        await expect(page).toHaveURL(/\/conversations\/[^/]+$/);
        expect(page.url()).not.toBe(firstUrl);
        await page
            .getByRole('link', { name: 'First conversation', exact: true })
            .click();
        await expect(page).toHaveURL(firstUrl);
        await expect(
            page.getByText('Test assistant response: First conversation', {
                exact: true,
            }),
        ).toBeVisible();
        await expect(
            page
                .locator('main')
                .getByText('[e2e:slow] Other conversation', { exact: true }),
        ).toHaveCount(0);
        await expect(page.getByPlaceholder('Type a message...')).toBeEnabled();
    });

    test('composer supports Shift Enter and ignores Enter while composing', async ({
        page,
    }) => {
        await register(page);
        const composer = page.getByPlaceholder('Type a message...');
        await composer.fill('First line');
        await composer.press('Shift+Enter');
        await composer.press('a');
        await expect(composer).toHaveValue('First line\na');
        await composer.dispatchEvent('keydown', {
            key: 'Enter',
            isComposing: true,
        });
        await expect(page).toHaveURL(/\/dashboard$/);
        await expect(composer).toHaveValue('First line\na');
    });

    test('deletion dialog traps focus and Escape restores the trigger', async ({
        page,
    }) => {
        await register(page);
        await send(page, 'Delete dialog test');
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
        await expect(
            dialog.getByRole('button', { name: 'Cancel', exact: true }),
        ).toBeFocused();
        await page.keyboard.press('Shift+Tab');
        await expect(
            dialog.getByRole('button', { name: 'Confirm', exact: true }),
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
});
