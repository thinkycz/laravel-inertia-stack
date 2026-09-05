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
- Official Laravel AI conversation remembering and tool approvals from remote main,
  adapted with the retained authentication, ownership and verification fixes.
- Direct `/agent/chat` Vercel streaming with `@ai-sdk/vue`, canonical conversation
  navigation and approve/edit/reject notification cards. The former custom runs,
  replay bridge, provider jobs and clarification flow are superseded.
- Recursive architecture scans and precise types replacing PHPStan suppressions;
  updated setup, architecture and lessons to match the implemented contracts.

Existing migrations target fresh installations. This does not authorize resetting
existing databases or establish compatibility for legacy-data upgrades. Live
provider and deployed-environment behavior require separate verification.

## [0.1.0] - 2026-06-07

Initial snapshot captured in `docs/verification/baseline-2026-06-07.md`.
14 tests / 45 assertions, Inertia 2 → 3 migration, PHP 8.3, Laravel 13.

## Audit finding traceability after the SDK architecture decision

The user selected remote main's official Laravel AI approval flow. Authentication,
scaffolding, strict analysis and tooling repairs remain. Custom durable-run fixes
are superseded rather than silently represented as equivalent SDK guarantees.
This table identifies the current verification target; final passing totals belong
in the integration handoff.

| Finding                                                        | Current correction or disposition                                                                  | Current verification target                                                                    |
| -------------------------------------------------------------- | -------------------------------------------------------------------------------------------------- | ---------------------------------------------------------------------------------------------- |
| 1. Cookie API lacked request-forgery protection                | Retained shared sessions/encryption, CSRF initializer, valid-bearer exemption and trusted origins  | CSRF and cross-surface auth tests                                                              |
| 2. Reset request changed passwords and emailed raw credentials | Retained reset-link-only recovery                                                                  | Web/API forgot/reset tests                                                                     |
| 3. Email changes retained verification                         | Retained invalidation only when the address changes                                                | Settings and API profile tests                                                                 |
| 4. Fixed verification token bypass                             | Retained bypass removal and token consumption                                                      | Web/API verification tests                                                                     |
| 5. Generated show leaked other users' records                  | Retained owner-scoped show/update/delete lookup                                                    | Disposable generator owner/other-user/guest tests                                              |
| 6. Vulnerable dependency chains                                | Retained patched dependencies and lockfiles; merged SDK/client versions pass fresh audits          | Composer/npm audits and locked installs                                                        |
| 7. SDK and application duplicated messages                     | SDK `RemembersConversations` is now the only message writer                                        | Actual stored rows and continued provider context through `/agent/chat`                        |
| 8. Concurrent starts/redelivery repeated execution             | Custom jobs and active-run state removed; a conversation lease serializes HTTP turns and approvals | Concurrent direct requests and duplicate/stale approval submission; no queued-redelivery claim |
| 9. New-chat second prompt created another conversation         | Adopt returned `x-conversation-id` for the next direct request                                     | Two-turn browser chat and persisted ownership                                                  |
| 10. Active run prop/subscription mismatch                      | `active_run` and the custom bridge removed; official SDK chat state replaces them                  | SDK message/history hydration and conversation navigation                                      |
| 11. Invalid settings rendered nonexistent components           | Retained Laravel redirect/session validation and Inertia `<Form>`                                  | Settings/auth validation and browser error states                                              |
| 12. Cookie encryption differed across surfaces                 | Retained consistent encryption/session/cookie attributes                                           | API-to-web authentication tests                                                                |
| 13. Confirmation composable ran outside setup                  | Retained setup-time confirmation initialization                                                    | Keyboard dialog and deletion scenarios                                                         |
| 14. Deletion left runs/events and allowed worker writes        | Run/event tables removed; participant cleanup and message foreign keys protect SDK history         | Account deletion, conversation deletion and writes racing deletion                             |
| 15. Optimistic cancellation stranded controls                  | Former cancellation endpoint removed; SDK Stop aborts browser transport                            | Controls recover after abort/error; no durable server-cancellation claim                       |
| 16. Clarifications were lost/unreachable                       | Clarification workflow intentionally replaced by official approvable notification tool             | Approve/edit/reject, pending-card refresh, stale and foreign decisions                         |
| 17. Operation throttles cleared successful starts              | Retain separate login/delivery budgets; adapt per-user chat quota to direct requests               | Validation/ownership failures and multi-IP per-user quota tests                                |
| 18. Local sync queue blocked streaming                         | Provider execution now streams in HTTP, not a queued/sync job                                      | Live local fake-provider stream; queue verification applies to mail only                       |
| 19. Raw errors leaked and abandoned runs remained active       | No durable runs to reconcile; direct-stream error handling must remain safe                        | Sanitized stream failures, transport abort and persisted approval state                        |
| 20. Deployment omitted assets and dependency reproducibility   | Retained locked installs, build-before-prune and manifest requirement                              | Deployment tests and disposable build/install verification                                     |

Additional findings and verification boundaries:

- Performance: there are no application per-delta database updates or replay-event
  writes. SDK context and repository UI history each select at most 100 messages,
  and the sidebar is limited to 50 conversations. The SDK retains streamed events
  in memory until response completion.
  The replacement native SDK lifecycle test verifies 200 emitted text deltas with
  fewer than 100 SQL statements. This fresh workload-specific result does not
  establish constant memory or universal throughput. The deleted 200-delta
  buffered-run benchmark is not current evidence. Generated pagination remains
  bounded to 25 by default and 100 maximum.
- Accessibility/localization: retain keyboard dialogs, composer/IME behavior and
  three-locale parity; adapt translated states to official tool approvals and
  SDK transport errors rather than removed run/clarification states.
- Dead code: remove superseded custom run models/jobs/events, clarification tool,
  bridge and pending-conversation singleton; retain the Axios removal and strict
  unused-symbol checks. Do not leave old lifecycle tests asserting removed APIs.
- Conventions: preserve recursive architecture scans, typed getters/scopes,
  imported classes, generator ownership rules and precise core contracts. Keep
  `HasConversations` and official SDK remembering as deliberate architecture.
- Test safety: owned temporary SQLite/server/session/cache artifacts, fake AI/mail,
  blocked external requests and no developer cache clearing remain mandatory.
  Approval side-effect tests must use the real SDK approval loop with fake
  transport, because `ChatAgent::fake()` bypasses parts of that loop.
- Scope: fresh-install schema adaptation does not authorize destructive migration
  of the current database. SDK polymorphic participant cleanup must be tested
  explicitly. Direct streams do not promise atomic message pairs, durable partial
  output, reconnect replay or exactly-once external effects without evidence.

Previous queued-run MySQL/Redis/browser totals and SQL measurements are historical
and must not be reused to certify the selected SDK flow. Re-run applicable checks
against the merged implementation. No paid provider or real deployment has been
exercised; production certification is outside this handoff.

Current SDK-specific regression evidence: `SdkConversationLifecycleTest` plus
`AgentChatControllerTest` passed 20 tests and 88 assertions. Coverage includes one
pair per normal turn, bounded context/history, unknown conversations, a fresh
200-delta SQL budget, null approval reason omission, safe exception/error/tool
error streams, pre-stream setup failure and lease release, and deletion of 105
owned conversations while preserving another participant's history. Fake
transport exercises the native SDK generation path without paid providers.

The selected adaptation uses a 180-second conversation request lease and
`SafeChatResponse` with a 90-second event-boundary elapsed check, disconnection
and deletion checks, safe protocol errors and explicit tool recipients. Process
termination after a tool side effect but before its SDK approval checkpoint
remains an exactly-once delivery limitation.

Current architecture checks passed 61 tests and 3,511 assertions. The latest
replacement disposable MySQL/Redis integration run reported five passing tests
and 42 assertions. Final `make fix` and `make check` passed, including 218 PHP tests (4,034
assertions), 17 Vitest tests, strict PHPStan, formatting, TypeScript and frontend
build. Composer and full npm dependency audits report no vulnerabilities. The
isolated Playwright suite passed all 28 tests. Environment-file hashes remain
unchanged; no application database reset, real deployment or paid provider call
was performed. Disconnect cleanup temporarily enables `ignore_user_abort`
around the stream callback, restores the prior value in `finally`, and releases
the conversation lease; it does not introduce a durable cancellation state.
