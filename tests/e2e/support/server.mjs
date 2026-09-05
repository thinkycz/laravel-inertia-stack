import { spawn } from 'node:child_process';
import { mkdtemp, mkdir, writeFile, rm } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import { join } from 'node:path';

const directory = await mkdtemp(join(tmpdir(), 'laravel-inertia-e2e-'));
const children = new Set();
let stopping = false;
const env = {
    PATH: process.env.PATH,
    TMPDIR: tmpdir(),
    E2E_DIRECTORY: directory,
    APP_ENV: 'testing',
    APP_NAME: 'Browser tests',
    APP_URL: 'http://127.0.0.1:18767',
    APP_KEY: 'base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=',
    APP_DEBUG: 'true',
    APP_CONFIG_CACHE: join(directory, 'config.php'),
    APP_ROUTES_CACHE: join(directory, 'routes.php'),
    APP_EVENTS_CACHE: join(directory, 'events.php'),
    APP_SERVICES_CACHE: join(directory, 'services.php'),
    APP_PACKAGES_CACHE: join(directory, 'packages.php'),
    DB_CONNECTION: 'sqlite',
    DB_DATABASE: join(directory, 'database.sqlite'),
    DB_HOST: '',
    DB_PORT: '',
    DB_USERNAME: 'unused',
    DB_PASSWORD: '',
    DB_SOCKET: '',
    REDIS_HOST: '',
    REDIS_PORT: '',
    REDIS_USERNAME: '',
    REDIS_PASSWORD: '',
    CACHE_DRIVER: 'file',
    SESSION_DRIVER: 'file',
    QUEUE_DRIVER: 'database',
    MAIL_MAILER: 'array',
    MAIL_HOST: '127.0.0.1',
    MAIL_PORT: '1',
    MAIL_USERNAME: 'unused',
    MAIL_PASSWORD: 'unused',
    MAIL_EHLO_DOMAIN: '',
    MAIL_FROM_ADDRESS: 'test@example.com',
    MAIL_FROM_NAME: 'Browser tests',
    AWS_ACCESS_KEY_ID: 'unused',
    AWS_SECRET_ACCESS_KEY: 'unused',
    AWS_DEFAULT_REGION: 'unused',
    OPENROUTER_API_KEY: 'fake-only',
    E2E_DISABLE_THROTTLE: 'true',
    PHP_CLI_SERVER_WORKERS: '4',
};

for (const path of [
    'framework/sessions',
    'framework/views',
    'framework/cache/data',
    'logs',
]) {
    await mkdir(join(directory, 'storage', path), { recursive: true });
}
await writeFile(env.DB_DATABASE, '');

function run(args) {
    const child = spawn('php', args, { env, stdio: 'inherit', detached: true });
    children.add(child);
    child.once('exit', () => children.delete(child));
    return child;
}

async function stop(code = 0) {
    if (stopping) return;
    stopping = true;
    for (const child of children) {
        try {
            process.kill(-child.pid, 'SIGTERM');
        } catch {
            /* Already exited. */
        }
    }
    const forceStop = setTimeout(() => {
        for (const child of children) {
            try {
                process.kill(-child.pid, 'SIGKILL');
            } catch {
                /* Already exited. */
            }
        }
    }, 5000);
    await Promise.all(
        [...children].map(
            (child) => new Promise((resolve) => child.once('exit', resolve)),
        ),
    );
    clearTimeout(forceStop);
    await rm(directory, { recursive: true, force: true });
    process.exit(code);
}
process.once('SIGINT', () => void stop());
process.once('SIGTERM', () => void stop());

const migration = run([
    'tests/e2e/support/artisan.php',
    'migrate',
    '--force',
    '--no-interaction',
]);
const migrationCode = await new Promise((resolve) =>
    migration.once('exit', resolve),
);
if (migrationCode !== 0) await stop(1);

for (const args of [
    [
        'tests/e2e/support/artisan.php',
        'queue:work',
        'database',
        '--sleep=1',
        '--tries=1',
        '--timeout=120',
    ],
    ['-S', '127.0.0.1:18767', '-t', 'public', 'tests/e2e/support/router.php'],
]) {
    run(args).once('exit', (code) => {
        if (!stopping) void stop(code || 1);
    });
}
