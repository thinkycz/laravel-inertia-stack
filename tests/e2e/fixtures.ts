import { test as base, expect } from '@playwright/test';

export const test = base.extend({
    context: async ({ context }, use) => {
        await context.route('**/*', async (route) => {
            const url = new URL(route.request().url());
            if (url.origin === 'http://127.0.0.1:18765') {
                await route.continue();
            } else {
                await route.abort('blockedbyclient');
            }
        });
        await use(context);
    },
});

export { expect };
export type { Page } from '@playwright/test';
