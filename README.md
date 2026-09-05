# Laravel Inertia Stack

Inertia-first Laravel 13 boilerplate with Vue 3, TypeScript, Tailwind, shadcn-vue-style primitives, and the Thinkycz Laravel core database-token guard.

## Development

```sh
composer install
npm ci
cp .env.example .env
php artisan key:generate
php artisan migrate
composer run dev
```

## Checks

```sh
npm run type-check
npm run build
composer test
make check
make e2e
```

Pest boots with isolated in-memory SQLite configuration. Playwright creates its
own temporary database, file sessions and fixed server with fake AI/mail
providers. Neither suite may reset the current database. `make check` and
`make e2e` are separate checks.

Chat streams directly from `POST /agent/chat` through Laravel AI's official
Vercel protocol to `@ai-sdk/vue`. The SDK remembers conversations and pauses
approvable notification tools until the user approves, edits or rejects them.
Queue workers deliver approved notifications; they do not execute chat streams.
See [architecture](docs/architecture.md) for persistence and interruption limits.

Commit both lockfiles. Deployment builds frontend assets before pruning build
dependencies and requires `public/build/manifest.json`. Test deployment commands
only in a disposable checkout.

## Routes

- `/login`, `/register`, `/forgot-password`, `/reset-password`
- `/dashboard`
- `/verify-email`
- `/settings` (unified profile + password page)

Minimal API-compatible auth endpoints remain under `/api/v1/auth`, `/api/v1/me`, `/api/v1/password`, and `/api/v1/email_verification`.

Application documentation is maintained in [docs/application_documentation.md](docs/application_documentation.md).
