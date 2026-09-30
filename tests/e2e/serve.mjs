import { spawn, spawnSync } from 'node:child_process';
import { randomBytes } from 'node:crypto';
import {
    mkdtempSync,
    mkdirSync,
    writeFileSync,
    readFileSync,
    rmSync,
} from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';

const port = process.argv[2] ?? '8173';
const ai = process.argv[3]?.startsWith('ai') ?? false;
const providerConfigured = process.argv[3] === 'ai';
const root = mkdtempSync(join(tmpdir(), 'starter-e2e-'));
for (const directory of ['sessions', 'cache', 'views'])
    mkdirSync(join(root, directory));
writeFileSync(join(root, 'database.sqlite'), '');
const defaults = Object.fromEntries(
    readFileSync('.env.example', 'utf8')
        .split('\n')
        .filter((line) => /^[A-Z_]+=/.test(line))
        .map((line) => {
            const index = line.indexOf('=');
            return [
                line.slice(0, index),
                line.slice(index + 1).replace(/^"|"$/g, ''),
            ];
        }),
);
mkdirSync('storage/framework/e2e', { recursive: true });
const registry = `storage/framework/e2e/${port}.json`;
writeFileSync(
    registry,
    JSON.stringify({
        database: join(root, 'database.sqlite'),
        mail: join(root, 'mail.log'),
    }),
);
const env = {
    ...process.env,
    ...defaults,
    APP_ENV: 'testing',
    APP_NAME: `Starter E2E ${port}`,
    APP_URL: `http://127.0.0.1:${port}`,
    APP_KEY: `base64:${randomBytes(32).toString('base64')}`,
    APP_DEBUG: 'true',
    APP_CONFIG_CACHE: join(root, 'config.php'),
    APP_ROUTES_CACHE: join(root, 'routes.php'),
    DB_CONNECTION: 'sqlite',
    DB_DATABASE: join(root, 'database.sqlite'),
    DB_HOST: '127.0.0.1',
    DB_PORT: '3306',
    DB_SOCKET: '',
    SESSION_DRIVER: 'file',
    SESSION_FILES: join(root, 'sessions'),
    SESSION_SECURE_COOKIE: 'false',
    CACHE_STORE: 'file',
    CACHE_PATH: join(root, 'cache'),
    VIEW_COMPILED_PATH: join(root, 'views'),
    QUEUE_CONNECTION: 'sync',
    MAIL_MAILER: 'log',
    MAIL_FROM_NAME: 'Starter E2E',
    MAIL_LOG_PATH: join(root, 'mail.log'),
    LOG_CHANNEL: 'single',
    REDIS_HOST: '127.0.0.1',
    REDIS_PORT: '6379',
    E2E_DISABLE_THROTTLE: 'true',
    AI_ENABLED: ai ? 'true' : 'false',
    OPENROUTER_API_KEY: providerConfigured ? 'e2e-fake-provider-key' : '',
    PHP_CLI_SERVER_WORKERS: '1',
};
// A brand-new database is migrated, never an existing application's database reset.
const migration = spawnSync(
    'php',
    ['artisan', 'migrate', '--force', '--no-interaction'],
    { env, stdio: 'inherit' },
);
if (migration.status !== 0) {
    rmSync(root, { recursive: true, force: true });
    process.exit(1);
}
const server = spawn(
    'php',
    ['artisan', 'serve', '--host=127.0.0.1', `--port=${port}`, '--no-reload'],
    { env, stdio: 'inherit' },
);
function stop() {
    server.kill('SIGTERM');
}
process.on('SIGTERM', stop);
process.on('SIGINT', stop);
server.on('exit', (code) => {
    rmSync(registry, { force: true });
    rmSync(root, { recursive: true, force: true });
    process.exit(code ?? 0);
});
