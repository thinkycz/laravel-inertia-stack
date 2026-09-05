# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

Pre-release boilerplate. Per-release changelog entries land under
their own `## [X.Y.Z]` heading at tag time.

The boilerplate currently ships:

- Laravel 13 on PHP 8.3, Inertia 3, Vue 3 (Composition API) + TypeScript,
  Tailwind 4, Vite.
- `packages/thinkycz/laravel-core` as a local path repository
  (helpers, guards, validation, scaffolding stubs, CRUD commands).
- Authentication via the core `database_token` guard over HTTP-only
  cookies. MustVerifyEmail, password reset, and email verification flows
  are wired end-to-end.
- Isolated Pest and Playwright verification, Vitest frontend tests and
  disposable generated CRUD contracts. Test counts belong in current verification
  output rather than a permanent claim in this changelog.
- Architecture-level enforcement: no inline FQNs in `routes/*`, no
  `ValidationException::withMessages` in `app/`, no `$request->session()->flash`
  in `app/` (use `Inertia::flash()` instead), no `env()` calls, multi-step
  persistence wrapped in `DB::transaction(...)`, every Web+Api controller
  has a matching feature test.
- PHPStan at `level: max` with `treatPhpDocTypesAsCertain: true`; Larastan's
  Eloquent magic builder exemption is configured in `phpstan.neon`.
- Three-locale i18n (en/cs/sk) parity enforced by
  `tests/Unit/I18nParityTest.php`.
- A test-owned Playwright server/database/worker with fake AI and mail providers,
  blocked external requests, and deterministic throttling configuration.

### Audit remediation

- Reproducible Composer/npm lockfiles, patched dependencies and removal of unused
  Axios initialization; test bootstrap no longer clears developer caches.
- Shared encrypted web/API cookie authentication, explicit CSRF initialization,
  valid-bearer exemptions and trusted-origin CORS configuration.
- Reset-link-only recovery, transactional token consumption/revocation and
  verification invalidation only when the email changes.
- Standard Inertia redirect/session validation with `<Form>` as the canonical
  pattern; separate login and operation quotas.
- Owned generated CRUD lookups, bounded pagination and executable disposable
  generator verification, including strict generated-code analysis.
- Application-owned AI persistence, guarded run lifecycle and uniqueness,
  bounded context/replay, buffered writes, cancellation and stale-run recovery.
- Canonical conversation navigation, persisted assistant reconciliation,
  clarification next turns and accessible keyboard/dialog interaction.
- Recursive architecture scans and precise types replacing PHPStan suppressions;
  updated setup, architecture and lessons to match the implemented contracts.

Existing migrations target fresh installations. This does not authorize resetting
existing databases or establish compatibility for legacy-data upgrades. Live
provider and deployed-environment behavior require separate verification.

## [0.1.0] - 2026-06-07

Initial snapshot captured in `docs/verification/baseline-2026-06-07.md`.
14 tests / 45 assertions, Inertia 2 → 3 migration, PHP 8.3, Laravel 13.

## Audit finding traceability

The following maps the original numbered findings to remediation and regression
coverage. A named test is coverage inventory, not an assertion that every test
has passed in the current checkout. Final integration results must accompany the
handoff.

| Finding                                                        | Correction                                                                                             | Verification boundary                                                    |
| -------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------ | ------------------------------------------------------------------------ |
| 1. Cookie API lacked request-forgery protection                | Shared sessions/encryption, CSRF initializer, valid-bearer-only exemption and explicit trusted origins | `CsrfShowControllerTest`, API auth tests; cross-origin request rejection |
| 2. Reset request changed passwords and emailed raw credentials | Reset links only; credentials remain unchanged until valid token completion                            | Web/API forgot/reset controller tests                                    |
| 3. Email changes retained verification                         | Clear verification only when the address changes                                                       | Settings and `MeUpdateControllerTest`                                    |
| 4. Fixed verification token bypass                             | Remove bypass and consume successful verification tokens                                               | Web confirmation and API verification tests                              |
| 5. Generated show leaked other users' records                  | Owner scope before show/update/delete lookup                                                           | `GeneratorContractTest`: owner, other user and guest cases               |
| 6. Vulnerable dependency chains                                | Patched compatible versions and committed lockfiles                                                    | Composer/npm audits and reproducible installation checks                 |
| 7. SDK and application duplicated messages                     | Application owns persistence; explicit completed context and one linked message pair                   | `AgentRunLifecycleTest`: persisted rows and provider context             |
| 8. Concurrent starts/redelivery repeated execution             | Unique active-conversation key, atomic claim, terminal guards and one attempt                          | Lifecycle tests plus disposable MySQL concurrent transitions             |
| 9. New-chat second prompt created another conversation         | Adopt canonical conversation ID and synchronize navigation                                             | `assistant.spec.ts`: two-turn new conversation                           |
| 10. Active run prop/subscription mismatch                      | Shared `active_run` snapshot and subscriptions tied to conversation/run                                | Bridge tests and refresh/navigation browser scenarios                    |
| 11. Invalid settings rendered nonexistent components           | Laravel redirect/session validation, canonical Inertia `<Form>`                                        | Settings/auth validation tests and profile browser flow                  |
| 12. Cookie encryption differed across surfaces                 | Common encrypted cookie/session middleware and attributes                                              | CSRF/cookie integration tests across API and web                         |
| 13. Confirmation composable ran outside setup                  | Initialize confirmation during setup                                                                   | Conversation deletion and keyboard dialog browser scenarios              |
| 14. Deletion left runs/events and allowed worker writes        | Cascading relationships and guarded writes after deletion                                              | Lifecycle deletion tests and MySQL foreign-key integration               |
| 15. Optimistic cancellation stranded controls                  | Pending acknowledgement, rejection handling and server/connection separation                           | Bridge tests and cancellation success/failure browser scenarios          |
| 16. Clarifications were lost/unreachable                       | Persist structured clarification; render choices as idempotent next turns                              | Clarification/lifecycle tests and browser selection scenario             |
| 17. Operation throttles cleared successful starts              | Separate failure budgets; validated successful-start quotas and message/IP limits                      | Start controller and password/verification delivery tests                |
| 18. Local sync queue blocked streaming                         | Local database queue with real worker; deployed Redis queue                                            | Isolated browser worker and Redis integration                            |
| 19. Raw errors leaked and abandoned runs remained active       | Safe translated errors, server reporting, dispatch failure and reconciliation                          | Lifecycle failure/recovery tests and Redis worker integration            |
| 20. Deployment omitted assets and dependency reproducibility   | Locked installs, build before pruning, required Vite manifest                                          | `deployment.test.ts` and disposable deployment sequence verification     |

Additional findings are covered separately:

- Performance: buffer text writes, bound context/replay and generated pagination;
  the deterministic 200-small-delta SQL test requires fewer than 100 statements.
  This is workload-specific evidence, not a general throughput claim.
- Accessibility/localization: named focus-managed dialogs, keyboard composer/IME
  behavior and en/cs/sk connection/tool/error states; browser keyboard scenarios,
  Vitest and locale-parity checks provide complementary evidence.
- Dead code: remove Axios bootstrap/types/dependency, unused AI context and
  pending-conversation state; consolidate persistence ownership. TypeScript
  unused-symbol checks, PHPStan and behavioral tests guard the replacements.
- Conventions/documentation: recursive architecture scans, imported classes,
  explicit model getters/scopes, generator rules and precise core types; corrected
  existing setup, guidelines, architecture and lessons.
- Verification safety: test-owned SQLite/server/session/cache/worker artifacts,
  fake providers, blocked external requests, lockfile installs and no developer
  cache clearing. `make check`, isolated Playwright and service integration are
  separate verification surfaces.

Final local verification: `make fix` and `make check` pass, including 212 Pest
checks (4,663 assertions), 35 Vitest tests, PHPStan at maximum strictness,
formatting, TypeScript, frontend build and clean Composer/npm dependency audits.
The isolated Playwright suite passes all 31 browser tests. `make test-integration`
passes five disposable MySQL 8.4/Redis tests (33 assertions), covering uniqueness,
foreign keys, concurrent starts/terminal transitions/deletion, real worker
execution, reconciliation and atomic delivery quotas. The 200-delta fake response
stays below 100 SQL statements.

Deployment ordering has two automated workflow tests plus a real disposable
locked npm install/build/manifest/prune check. Composer and Artisan deployment
operations were simulated. Paid-provider behavior and deployment against a real
environment were not exercised. Existing databases and `.env*` files were not
changed; migrations describe fresh installations only.
