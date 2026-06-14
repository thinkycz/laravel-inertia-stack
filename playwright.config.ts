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
        baseURL: process.env.APP_URL ?? 'http://127.0.0.1:8000',
        trace: 'on-first-retry',
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
        command:
            'php artisan optimize:clear && php artisan migrate:fresh --env=testing --force && php artisan serve --host=127.0.0.1 --port=8000',
        url: 'http://127.0.0.1:8000',
        reuseExistingServer: !process.env.CI,
        timeout: 60000,
        env: {
            APP_ENV: 'testing',
            CACHE_STORE: 'array',
            SESSION_SECURE_COOKIE: 'false',
            MAIL_MAILER: 'log',
            E2E_DISABLE_THROTTLE: 'true',
        },
    },
});
