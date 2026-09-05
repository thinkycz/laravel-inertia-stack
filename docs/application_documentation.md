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
- Database queue for local notifications; Redis for deployed cache/session/queue.
- Direct HTTP streaming for chat; provider execution is not queued.
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

`ChatAgent` uses Laravel AI's official `RemembersConversations`, and `User` uses
its polymorphic `HasConversations` relation. The SDK owns message persistence;
`ConversationRepository` handles owned lookup and UI serialization. The frontend
uses `@ai-sdk/vue` with the Vercel UI-message protocol from `POST /agent/chat`.

The endpoint accepts a message or approval decisions for the same owned
conversation. `SendTestNotificationTool` requires approval; users can approve,
edit the subject/message, or reject. The recipient is the explicitly supplied conversation participant; approved
notifications are queued after commit. Stored tool parts and approval state restore cards on refresh. The former
clarification cards, durable runs, replay events and recovery worker are removed.

A 180-second conversation request lease protects normal/approval submissions.
`SafeChatResponse` sanitizes error events, omits null approval reasons for AI SDK 7
and checks the 90-second stream deadline, disconnects and deletion at event
boundaries. It does not guarantee exactly-once side effects across process crashes.

Provider calls execute in the streaming HTTP request. Workers deliver queued
notifications and other mail. A stopped or disconnected stream does not have an
acknowledged durable cancellation state; refresh restores only SDK-persisted
history. Read [architecture](architecture.md) for boundaries that require specific
concurrency, deletion, failure and approval tests.

Pest selects SQLite `:memory:` before bootstrap. Playwright owns temporary SQLite,
file sessions and a fixed server with fake AI/mail providers. Tests must exercise
the official approval loop through fake transport when tool execution matters.
Neither environment may reset the current database, edit environment files or
contact paid providers. Fresh-install migrations are not legacy-data conversions.
Verify deployment in a disposable checkout and report current MySQL/Redis results
separately from provider-fake/browser checks.
