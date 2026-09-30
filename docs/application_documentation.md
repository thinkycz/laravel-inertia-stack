# Application documentation

## Stack and setup

The starter uses Laravel 13.34, Inertia Laravel 3.4 / Vue 3.7, Laravel AI 1, Vue 3.5, Tailwind 4, Vite 8, Vitest 5, TypeScript 6, and Vue I18n 11. PHP 8.3 is the Composer resolution target; Pest 4 and PHPUnit 12 remain compatible. Node 24 is required. TypeScript 7 is deferred until Vue tooling supports its compiler entry points.

Run `make setup`, configure `.env`, then `make local`. Setup preserves existing values and application keys. Migrations preserve data. New projects do not seed demo accounts automatically. `make demo` explicitly seeds local data; non-local seeding is rejected.

## Configuration

| Variable                                                                       | Purpose / default                                                   |
| ------------------------------------------------------------------------------ | ------------------------------------------------------------------- |
| `APP_NAME`, `APP_URL`, `APP_ENV`                                               | Branding, mail-link origin, environment                             |
| `APP_KEY`                                                                      | Unique encryption key; generated only when missing                  |
| `DB_CONNECTION`                                                                | MySQL by default; SQLite supported for local/tests                  |
| `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`, `DB_SOCKET` | Database connection                                                 |
| `CACHE_STORE`                                                                  | File locally, array in PHP tests, Redis in deployed environments    |
| `QUEUE_CONNECTION`                                                             | Sync locally/tests; Redis in deployed environments                  |
| `SESSION_DRIVER`                                                               | File locally, array in PHP tests, Redis in deployed environments    |
| `MAIL_MAILER`                                                                  | Log locally, array in tests; delivery mailer required in production |
| `MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME`                                          | Sender details                                                      |
| `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`                     | SMTP configuration when selected                                    |
| `REDIS_HOST`, `REDIS_PORT`, `REDIS_USERNAME`, `REDIS_PASSWORD`                 | Redis connection                                                    |
| `AUTH_TOKEN_LIFETIME_DAYS`                                                     | Absolute login-token lifetime; defaults to 30 days                  |
| `AI_ENABLED`                                                                   | Optional assistant; defaults to false                               |
| `AI_PROVIDER` and provider credentials                                         | Default provider is OpenRouter; credentials are required            |
| `OPENROUTER_MODEL`                                                             | Model for the default OpenRouter provider                           |
| `REDIS_QUEUE_RETRY_AFTER`                                                      | Queue retry delay; defaults to 90 seconds                           |
| `WORKER_TIMEOUT`                                                               | Supervisor wrapper timeout; defaults to 60 seconds                  |

Legacy `CACHE_DRIVER`, `QUEUE_DRIVER`, `MAIL_DRIVER`, and `LOG_DRIVER` aliases remain accepted when their standard counterpart is absent. Production rejects log/array mailers. The worker wrapper rejects a timeout greater than or equal to the retry delay; configure these together. `SESSION_FILES`, `CACHE_PATH`, `MAIL_LOG_PATH`, and `VIEW_COMPILED_PATH` allow browser-test storage isolation.

## Account workflows

The dashboard is an account overview with verification status and links to profile, security, and appearance. Settings lists active sessions, marks the current device, confirms passwords for other-session revocation and account deletion, and stores appearance on the device. Password changes explicitly return to sign-in after revoking all tokens.

Recovery sends expiring reset links, never generated passwords. Unknown-account responses remain neutral. Registration sends an initial verification message; changing email clears verification and sends a fresh message after commit. Valid requests retain throttle counts. Standard web validation redirects with scoped form errors; JSON API error contracts remain compatible.

## Optional assistant and upgrades

Enable `AI_ENABLED=true` and configure provider credentials to expose `/assistant`. Conversation URLs and `/agent/chat` remain supported. Without credentials the UI explains availability and disables sending. AI history must use the application database so account deletion stays transactional. Ordinary overview/settings requests omit history; disabled AI avoids conversation queries entirely.

The SDK 1 migration retains message content and legacy tool columns while converting steps/status. Incompatible schemas stop with an actionable error before changing their tables. Review and explicitly migrate unsupported legacy schemas before upgrading; no reset/drop shortcut is provided.

The token migration preserves hashes, adds metadata, and gives existing sessions a configured 30-day transition. A scheduled pruning job removes expired records. Run scheduler and queue workers in deployed environments.

## Validation and release

`make fix` followed by `make check` is the repository gate: PHPStan max, Prettier/Pint, full Composer/npm audits, platform/manifest checks, strict Vue type checks, production build, Vitest, and Pest with warnings/risky tests treated as failures. `make test` is self-contained and does not touch application caches. `make e2e` builds then runs Chromium with disposable databases and captured mail.

`phpunit.integration.xml` intentionally targets only `starter_integration_tests`. Create it explicitly and supply the host/Redis port before running it. Do not point it at an application database. GitLab adds PHP 8.3, Node 24, MySQL 8/Redis, and browser validation alongside the existing shared pipeline.

`make development`, `make staging`, and `make production` build assets while development tools exist, migrate without seeding, then prune development dependencies and optimize. Supply production configuration first. These commands prepare a release; this pass does not deploy it.

## Delivery evidence

Verified on 30 September 2026 with Node 24 and PHP 8.3.33. The temporary PHP runtime was used without changing the installed interpreter or Herd configuration.

| Check                             | Result                                                                                                                                                           |
| --------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `make fix` → `make check`         | Passed: PHPStan max, formatting, platform checks, both complete dependency audits, strict type checks, and production build                                      |
| Pest                              | 215 tests, 2,819 assertions; no warnings or risky tests                                                                                                          |
| Vitest                            | 12 tests passed                                                                                                                                                  |
| Chromium                          | 36 workflows passed against disposable PHP 8.3 servers                                                                                                           |
| MySQL 8 / Redis                   | 25 tests, 108 assertions; dedicated database and Redis instance                                                                                                  |
| Upgrade rehearsal                 | Existing token hashes, conversation content, and approval state preserved; incompatible schemas rejected before mutation                                         |
| Fresh install / release rehearsal | Unique keys, quoted empty key handling, repeatable setup, preserved configuration/lockfiles, no implicit demo data, built assets before development-tool removal |

The original checkout could not boot without supplied configuration and its PHP tests depended on local environment values. It had three Composer advisories, npm advisories, 188 test warnings, and two risky tests. This delivery addresses those findings alongside redirect-based validation, neutral reset-link recovery, verification/throttle fixes, expiring login tokens, owned session/account operations, optional AI, and safe schema upgrades.

Rendered evidence is saved in ignored `storage/app/verification/`: `before-login.png`, `after-login.png`, all 24 overview/settings combinations of desktop/mobile, light/dark, and en/cs/sk, long assistant responses, and `setup-rehearsal.json`. Browser checks cover captured recovery/verification mail, email changes, expired sessions, session ownership/revocation, password changes, deletion, appearance persistence, keyboard navigation/focus, and AI disabled/configured/unconfigured states. AI streams, errors, interruptions, and approval flows use fakes rather than external provider requests.

The tested dependency combination keeps TypeScript 6.0.3 with vue-tsc 3.3.11: TypeScript 7 currently prevents that Vue checker from starting. Laravel 13, Pest 4, and PHPUnit 12 remain compatible with PHP 8.3. GitLab PHP 8.3 jobs are defined but have not been run remotely during this local delivery. External provider credentials, production mail delivery, and production deployment still require operator configuration.

SSR, billing, teams, and business-specific sample modules remain deferred.
