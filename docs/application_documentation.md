# Application documentation

## Tech stack

- PHP 8.3
- Laravel 13
- Inertia 3
- Vue 3 with TypeScript
- Tailwind 4
- Composer 2
- Node 22 or newer recommended for Vite tooling

## Packages

| package                     | description                           |
| --------------------------- | ------------------------------------- |
| `thinkycz/laravel-core`     | internal Laravel core package         |
| `inertiajs/inertia-laravel` | Laravel server adapter for Inertia    |
| `@inertiajs/vue3`           | Vue client adapter for Inertia        |
| `@inertiajs/vite`           | Inertia Vite integration              |
| `vue`                       | frontend framework                    |
| `tailwindcss`               | styling system                        |
| `clsx`                      | class name composition helper         |
| `tailwind-merge`            | resolves conflicting Tailwind classes |

## Runtime services

- MySQL 8 for persistent local/deployed environments.
- Database queue for local asynchronous chat; Redis for deployed cache/session/queue.
- Cron for Laravel scheduler.
- Supervisor for queue workers.

## HTTP surfaces

- Inertia web app:
    - `/login`, `/register`, `/forgot-password`, `/reset-password`
    - `/dashboard`
    - `/verify-email`
    - `/settings` (unified profile + password page)
- Minimal API compatibility:
    - `/api/v1/auth/*`
    - `/api/v1/me/*`
    - `/api/v1/password/*`
    - `/api/v1/email_verification/*`

## Authentication

- Default guard: `users`.
- Guard driver: `database_token`.
- Login/register issue an HTTP-only bearer cookie through `Thinkycz\LaravelCore\Guards\DatabaseTokenGuard`.
- Inertia pages receive the current user through `HandleInertiaRequests` shared props.
- Web forms use Inertia `<Form>` with Laravel redirects, session validation errors and `Inertia::flash`.
- API cookie clients initialize cookies through `GET /api/v1/auth/csrf` (204). Cookie mutations require CSRF; only successfully authenticated explicit bearer tokens are exempt.
- Password recovery sends reset links only. Requesting a reset does not mutate credentials. Email changes clear verification and successful verification consumes the token.
- Both surfaces encrypt cookies identically. Same-origin access is the default; configure `TRUSTED_ORIGINS` explicitly when needed.

## Cookies

| name pattern                                      | description                  |
| ------------------------------------------------- | ---------------------------- |
| `{app_name}_{env}_database_token_users`           | local/testing bearer token   |
| `__Host-{app_name}_{env}_database_token_users`    | secure deployed bearer token |
| `{app_name}_{env}_session` / `__Host-..._session` | Laravel session/CSRF use     |

## Tooling

| command              | description                                       |
| -------------------- | ------------------------------------------------- |
| `composer run dev`   | Laravel server, queue, logs, Vite                 |
| `npm run dev`        | Vite development server                           |
| `npm run type-check` | Vue TypeScript check                              |
| `npm run build`      | production frontend build                         |
| `composer test`      | Laravel test suite                                |
| `make fix`           | Prettier and Pint formatting                      |
| `make check`         | static analysis, lint, audit, build, Vitest, Pest |
| `make e2e`           | isolated Playwright browser tests                 |

## Env

Copy `.env.example` to `.env` and set:

- `APP_NAME`
- `APP_ENV`
- `APP_KEY`
- `APP_URL`
- `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`
- `REDIS_USERNAME`, `REDIS_PASSWORD` when Redis requires credentials
- `MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME`
- `TRUSTED_PROXIES`

## Deferred

- Inertia SSR is intentionally not enabled in v1.
- OpenAPI demo generation from the reference project is intentionally omitted.
- Catalog/order sample entities from the reference project are intentionally omitted.

## Conversation execution and verification

Application repositories own run/message persistence. The SDK receives explicit
prior context, without automatic remembering. A submitted prompt creates one
user message and assistant placeholder linked to a run. Active-run uniqueness,
atomic claims and guarded terminal transitions prevent duplicate execution and
completion/cancellation races. Clarifications complete the run and selections
start the next turn. See [architecture](architecture.md) for persistence,
stream buffering, client reconciliation and recovery details.

Provider/worker/visibility timeouts are 90/120/180 seconds. Reconciliation fails
queued work after five minutes and running work after 150 seconds. Start the
scheduler as well as workers in deployed environments; retries are new user runs.

Pest selects SQLite `:memory:` before bootstrap. Playwright owns a temporary
SQLite database, file sessions and fixed server, using fake AI/mail providers.
Tests do not reset the current database, modify environment files or contact paid
providers. Existing migrations target fresh installs; this is not an upgrade
conversion for existing data. Verify deployment in a disposable checkout and
record MySQL/Redis integration results separately from local fake-provider tests.

Service concurrency and worker checks run with `make test-integration`. This command
starts disposable MySQL 8.4 (Docker) and Redis processes, uses only test-owned
data, and removes those services afterward. It requires Docker and `redis-server`
on the verification host.
