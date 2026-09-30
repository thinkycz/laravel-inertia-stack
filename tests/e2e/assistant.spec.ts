import { expect, test, type Page, type Route } from '@playwright/test';

async function register(page: Page) {
    const email = `assistant-${Date.now()}-${Math.random().toString(36).slice(2)}@example.com`;

    await page.goto('/register');
    await page.getByLabel('Email').fill(email);
    await page.getByLabel('Password', { exact: true }).fill('password1');
    await page.getByLabel('Confirm password').fill('password1');
    await page.getByLabel('Locale').selectOption('en');
    await page.getByRole('button', { name: 'Register' }).click();
    await page.waitForURL(/\/dashboard/);
    await page.goto('/assistant');
}

async function fulfillStream(route: Route, parts: object[]) {
    await route.fulfill({
        status: 200,
        headers: {
            'Content-Type': 'text/event-stream',
            'x-vercel-ai-ui-message-stream': 'v1',
        },
        body: `${parts.map((part) => `data: ${JSON.stringify(part)}\n\n`).join('')}data: [DONE]\n\n`,
    });
}

function textFixture(text: string): object[] {
    return [
        { type: 'start', messageId: 'assistant-message' },
        { type: 'text-start', id: 'text-part' },
        { type: 'text-delta', id: 'text-part', delta: text },
        { type: 'text-end', id: 'text-part' },
        { type: 'finish', finishReason: 'stop' },
    ];
}

function approvalContinuationFixture(text: string, denied = false): object[] {
    return [
        { type: 'start', messageId: 'assistant-approval' },
        {
            type: denied ? 'tool-output-denied' : 'tool-output-available',
            toolCallId: 'notification-call',
            ...(denied ? {} : { output: 'Notification queued.' }),
        },
        { type: 'text-start', id: 'continuation-text' },
        { type: 'text-delta', id: 'continuation-text', delta: text },
        { type: 'text-end', id: 'continuation-text' },
        { type: 'finish', finishReason: 'stop' },
    ];
}

function approvalFixture(): object[] {
    return [
        { type: 'start', messageId: 'assistant-approval' },
        {
            type: 'tool-input-available',
            toolCallId: 'notification-call',
            toolName: 'SendTestNotificationTool',
            input: { subject: 'Original subject', message: 'Original message' },
        },
        {
            type: 'tool-approval-request',
            toolCallId: 'notification-call',
            approvalId: 'notification-call',
        },
        { type: 'finish', finishReason: 'tool-calls' },
    ];
}

test.describe('Assistant chat UI', () => {
    test('mobile users can open conversation history', async ({ page }) => {
        await page.setViewportSize({ width: 390, height: 844 });
        await register(page);

        await expect(page.getByText('No conversations yet')).toBeVisible();
    });

    test('renders an ordinary Vercel protocol response', async ({ page }) => {
        await register(page);
        await page.route('**/agent/chat', (route) =>
            fulfillStream(route, textFixture('Hello from the assistant')),
        );

        await page.getByPlaceholder('Type a message...').fill('Hello');
        await page.keyboard.press('Enter');

        await expect(page.getByText('Hello from the assistant')).toBeVisible();
    });

    test('allows notification arguments to be edited before approval', async ({
        page,
    }) => {
        await register(page);
        let continuationBody: Record<string, unknown> | null = null;

        await page.route('**/agent/chat', async (route) => {
            const body = route.request().postDataJSON() as Record<
                string,
                unknown
            >;

            if ('decisions' in body) {
                continuationBody = body;
                await fulfillStream(
                    route,
                    approvalContinuationFixture('Notification queued'),
                );

                return;
            }

            await fulfillStream(route, approvalFixture());
        });

        await page
            .getByPlaceholder('Type a message...')
            .fill('Send a test notification');
        await page.keyboard.press('Enter');

        await expect(
            page.getByRole('region', { name: 'Approve test notification' }),
        ).toBeVisible();
        await page.getByLabel('Subject').fill('Edited subject');
        await page
            .getByRole('textbox', { name: 'Message', exact: true })
            .fill('Edited message');
        await page.getByRole('button', { name: 'Approve and send' }).click();

        await expect(
            page.getByText('Notification queued', { exact: true }),
        ).toBeVisible();
        expect(continuationBody).toMatchObject({
            decisions: {
                'notification-call': {
                    action: 'approve',
                    arguments: {
                        subject: 'Edited subject',
                        message: 'Edited message',
                    },
                },
            },
        });
    });

    test('submits an optional rejection explanation', async ({ page }) => {
        await register(page);
        let continuationBody: Record<string, unknown> | null = null;

        await page.route('**/agent/chat', async (route) => {
            const body = route.request().postDataJSON() as Record<
                string,
                unknown
            >;

            if ('decisions' in body) {
                continuationBody = body;
                await fulfillStream(
                    route,
                    approvalContinuationFixture('Understood', true),
                );

                return;
            }

            await fulfillStream(route, approvalFixture());
        });

        await page
            .getByPlaceholder('Type a message...')
            .fill('Send a test notification');
        await page.keyboard.press('Enter');
        await page
            .getByLabel('Reason for rejection (optional)')
            .fill('Use a shorter message.');
        await page.getByRole('button', { name: 'Reject' }).click();

        await expect(
            page.getByText('Understood', { exact: true }),
        ).toBeVisible();
        expect(continuationBody).toMatchObject({
            decisions: {
                'notification-call': {
                    action: 'reject',
                    result: 'Use a shorter message.',
                },
            },
        });
    });

    test('renders a Vercel protocol stream error', async ({ page }) => {
        await register(page);
        await page.route('**/agent/chat', (route) =>
            fulfillStream(route, [
                { type: 'start', messageId: 'assistant-error' },
                {
                    type: 'error',
                    errorText: 'Failed to generate response. Please try again.',
                },
            ]),
        );

        await page
            .getByPlaceholder('Type a message...')
            .fill('Hello failing AI');
        await page.keyboard.press('Enter');

        await expect(
            page.getByText('Failed to generate response. Please try again.'),
        ).toBeVisible();
    });
});

test('an interrupted request returns the composer to a usable state', async ({
    page,
}) => {
    await register(page);
    let release: () => void = () => {};
    const pending = new Promise<void>((resolve) => {
        release = resolve;
    });
    await page.route('**/agent/chat', async (route) => {
        await pending;
        await route.abort('aborted').catch(() => {});
    });
    await page.getByPlaceholder('Type a message...').fill('A slow response');
    await page.keyboard.press('Enter');
    const stop = page.getByRole('button', { name: 'Stop response' });
    await expect(stop).toBeVisible();
    await stop.click();
    await expect(stop).toHaveCount(0);
    await expect(page.getByPlaceholder('Type a message...')).toBeEnabled();
    release();
});

test('long responses wrap without clipping on desktop and mobile', async ({
    page,
}) => {
    await register(page);
    const text = `Reference ${'x'.repeat(600)}\n\n${'A longer paragraph with readable spacing. '.repeat(50)}`;
    await page.route('**/agent/chat', (route) =>
        fulfillStream(route, textFixture(text)),
    );
    await page
        .getByPlaceholder('Type a message...')
        .fill('Show a long response');
    await page.keyboard.press('Enter');
    const paragraph = page.getByText(text, { exact: true });
    await expect(paragraph).toBeVisible();
    for (const mobile of [false, true]) {
        await page.setViewportSize({
            width: mobile ? 390 : 1440,
            height: mobile ? 844 : 1000,
        });
        expect(
            await paragraph.evaluate(
                (element) => element.scrollWidth <= element.clientWidth,
            ),
        ).toBe(true);
        expect(
            await page.evaluate(
                () => document.documentElement.scrollWidth <= innerWidth,
            ),
        ).toBe(true);
        await page.screenshot({
            path: `storage/app/verification/assistant-long-${mobile ? 'mobile' : 'desktop'}.png`,
            fullPage: true,
        });
    }
});
