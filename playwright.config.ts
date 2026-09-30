import { defineConfig, devices } from '@playwright/test';

export default defineConfig({
    testDir: './tests/e2e',
    fullyParallel: false,
    forbidOnly: !!process.env.CI,
    retries: process.env.CI ? 2 : 0,
    workers: 1,
    reporter: [['list'], ['html', { open: 'never' }]],
    timeout: 30000,
    use: {
        ...devices['Desktop Chrome'],
        trace: 'retain-on-failure',
        screenshot: 'only-on-failure',
        actionTimeout: 10000,
        navigationTimeout: 15000,
    },
    projects: [
        {
            name: 'chromium',
            testIgnore: ['**/assistant.spec.ts', '**/availability.spec.ts'],
            use: { baseURL: 'http://127.0.0.1:8173' },
        },
        {
            name: 'assistant',
            testMatch: '**/assistant.spec.ts',
            use: { baseURL: 'http://127.0.0.1:8174' },
        },
        {
            name: 'availability',
            testMatch: '**/availability.spec.ts',
            use: { baseURL: 'http://127.0.0.1:8176' },
        },
    ],
    webServer: [8173, 8174, 8176].map((port) => ({
        command: `node tests/e2e/serve.mjs ${port} ${port === 8174 ? 'ai' : port === 8176 ? 'ai-unconfigured' : ''}`,
        url: `http://127.0.0.1:${port}`,
        reuseExistingServer: false,
        timeout: 60000,
    })),
});
