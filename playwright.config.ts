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
        baseURL: 'http://127.0.0.1:18767',
        trace: 'on-first-retry',
        serviceWorkers: 'block',
        screenshot: 'only-on-failure',
        actionTimeout: 10000,
        navigationTimeout: 15000,
    },
    projects: [
        {
            name: 'chromium',
            use: { ...devices['Desktop Chrome'] },
        },
    ],
    webServer: {
        command: 'node tests/e2e/support/server.mjs',
        url: 'http://127.0.0.1:18767/login',
        reuseExistingServer: false,
        gracefulShutdown: { signal: 'SIGTERM', timeout: 10000 },
        timeout: 60000,
    },
});
