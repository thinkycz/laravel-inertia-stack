# Laravel Inertia Stack

A Laravel 13 / Inertia 3 application starter with Vue 3, TypeScript, Tailwind 4, and the local Thinkycz core package. It opens into an account overview, with complete account settings and an optional assistant. English, Czech, and Slovak are included.

## Development

Requirements: PHP 8.3+, Composer 2, Node 24, and MySQL 8. Redis is used for deployed sessions, cache, and queues.

```sh
make setup
# Configure APP_URL and database credentials in .env.
make local
composer run dev
```

`make setup` creates missing configuration, generates a unique key only when no key exists, and installs both lockfiles. `make local` migrates without resetting data and builds frontend assets. Demo data is opt-in with `make demo`, permitted only with `APP_ENV=local`. For a small local sandbox, set `DB_CONNECTION=sqlite` and `DB_DATABASE` to an existing, absolute SQLite file path.

## Checks

```sh
make fix
make check
npx playwright install chromium
make e2e
```

PHP tests use in-memory SQLite and a separate environment file. Browser tests create temporary SQLite databases, isolated servers on ports 8173/8174/8176 for disabled, configured, and unconfigured AI, session/cache directories, and captured mail. They never reuse a running application server. The integration configuration uses only the disposable database `starter_integration_tests`:

```sh
DB_HOST=127.0.0.1 REDIS_PORT=6379 php -d zend.assertions=1 -d memory_limit=1G vendor/bin/pest --configuration=phpunit.integration.xml
```

Create that empty MySQL database explicitly before running integration tests. GitLab jobs validate PHP 8.3, Node 24, MySQL 8/Redis, and Chromium.

## Routes and features

- `/login`, `/register`, `/forgot-password`, `/reset-password`
- `/dashboard`: account overview and useful shortcuts
- `/settings`: profile, password, active sessions, appearance, and account deletion
- `/verify-email`: resend verification
- `/assistant`: optional assistant; existing `/conversations/{id}` URLs are retained

AI defaults to disabled. Enable `AI_ENABLED=true` and configure `AI_PROVIDER` and its provider credentials to use it. Missing credentials show an unavailable state. AI bundles and history are loaded only within the assistant workspace.

Login tokens expire after `AUTH_TOKEN_LIFETIME_DAYS` (30 by default). Password changes revoke every session and return to sign-in. Revoking another session and deleting the account require the current password. Appearance is saved on each device.

API-compatible endpoints retain their existing URLs and payloads under `/api/v1/auth`, `/api/v1/me`, `/api/v1/password`, and `/api/v1/email_verification`.

See [application documentation](docs/application_documentation.md) for configuration/release details and [architecture](docs/architecture.md) for implementation contracts. SSR, billing, teams, business modules, and deployment are outside this starter pass.
