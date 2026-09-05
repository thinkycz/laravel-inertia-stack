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

Local chat uses the database queue; keep the worker started by `composer run dev`
running. Deployed environments require both a Redis worker and Laravel scheduler.
See [architecture](docs/architecture.md) for timeout and recovery contracts.

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
