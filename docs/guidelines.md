# Laravel Core Boilerplate Guidelines

This project is an **Inertia-web** app at the UI surface, with a small
**JSON:API** surface for programmatic clients. The conventions below cover
both. Items that apply only to one surface are tagged **[Web]** or **[API]**.

## Stack and Tooling

- Makefile is the primary entry point for provisioning, formatting, and
  validation: `make local|testing|development|staging|production`, `make fix`,
  `make check`.
- Before each commit, run `make fix` then `make check`.
- PHPStan is the source of truth for type errors. It must stay at
  `level: max` with `treatPhpDocTypesAsCertain: true` and pass without
  `phpstan-baseline.neon`. Do not lower strictness, reintroduce a baseline,
  add broad ignores, or suppress errors in code; fix the underlying type or
  API contract issue. Eloquent's magic builder chains are exempt from
  strict-rules' `dynamicCallOnStaticMethod` warning (see `phpstan.neon`).
- Frontend type checks: `npm run type-check`; build: `npm run build`.
- Vitest unit tests: `npm run test:unit` (run as part of `make check`).

## Consistency

- Code must be consistent within the project and with the local
  `thinkycz/laravel-core` package.
- Follow existing project conventions for naming, imports, and
  control flow. When you find a violation, fix it before adding the new
  feature.
- Import every PHP class/interface/trait/enum reference with a `use`
  statement. Inline fully qualified class names are not allowed in
  signatures, route definitions, PHPDoc, catches, callbacks, or method
  bodies when the symbol can be imported. Global function calls such as
  `\array_map()` may remain fully qualified when used deliberately.
- Do not use model `@property`, `@method`, or `@phpstan-method` PHPDoc
  as a substitute for real APIs. Eloquent attributes are read through
  explicit getters that use `assertString`, `assertInt`,
  `assertNullableString`, `Typer::*`, or the closest precise assertion.
- Relations are accessed through explicit relationship methods for query
  building or through typed relation getters such as `getStore()` and
  `getMovementItems()`. Application code does not read
  `$model->relation` properties.
- Local scopes are called directly, for example
  `Item::scopeSearch($query, $search)` or from a `tap()` callback that
  calls the static scope method. Do not rely on magic builder methods
  such as `$query->search()` or `$query->forUser()`.
- PHPDoc remains appropriate for real generic contracts, including
  relationship return types, `@param Builder<Model>`, and
  `@use HasFactory<Factory>`.
- Avoid single-use temporary variables for obvious expressions. Inline
  trivial values such as `'%' . $search . '%'`, and remove unused
  locals immediately.
- TypeScript must keep `noUnusedLocals` and `noUnusedParameters`
  enabled. Remove confirmed unused imports, locals, and dependencies
  instead of leaving dead frontend code behind.

## Architecture Tests

`tests/Architecture/*` enforces project conventions. Reading them is
the fastest way to learn the rules. Common enforced conventions:

- Controller suffixes, model/validity/visibility naming.
- Web controllers may use `create/store` and `edit/update` pairs.
- API controllers must extend `AutomaticController` and be invokable
  with `__invoke(ApiFormRequest $request): SymfonyResponse`.
- All Enums end with `Enum` suffix and are string-backed.
- All Resources extend `JsonApiResource`.
- Only `Resolver::resolveRouteRegistrar()` may be used in `routes/*`; no
  raw `Route::*()`.
- Validity classes use `BaseValidity` and only declare wrapper helpers —
  no direct `->required()` / `->nullable()` chains.
- Models under `App\Models` extend `BaseModel` or `BaseUser`; non-User
  models must implement `querySelect()` and `scopeSearch()`.
- Strict equality only (`===`/`!==`), no `==`/`!=`.
- No `env()`, `config()`, `dd()`, `var_dump()`, `unserialize()`, etc.
  outside config files.
- Do not call `env()` or `\env()` directly, including in config files.
  Read environment values through `$env = Env::inject();` and use the
  appropriate typed parser/assertion method.
- Every controller under `app/Http/Controllers/{Web,Api}/**/*.php`
  (except `Concerns/`) must have a matching `*ControllerTest.php` under
  `tests/Feature/.../`. Enforced by `CoverageArchitectureTest`.

When in doubt, read the test that enforces the rule.

## Tests

- Every controller must have at least one feature test. 100% coverage
  of the success path is expected; error path coverage is recommended
  but not mandatory.
- Test files mirror the source tree:
  `app/Http/Controllers/Web/Auth/LoginController.php` →
  `tests/Feature/App/Http/Controllers/Web/Auth/LoginControllerTest.php`.
- Use the `UserFactory` to create users in tests; reach for
  `User::query()->create([...])` only when exercising persistence
  paths that need a real database row rather than a factory state.
- The `assertInertiaFlash(TestResponse $response, string $key, mixed
$message)` helper asserts Inertia flash messages for both
  redirect and 200-OK render responses.

## HTTP Surfaces

| Surface | Path          | Returns               | Notes     |
| ------- | ------------- | --------------------- | --------- |
| Inertia | `/...`        | Inertia render or 302 | **[Web]** |
| API     | `/api/v1/...` | `JsonApiResource`     | **[API]** |

- **[Web]** Inertia controllers live in
  `app/Http/Controllers/Web/...` and return `Inertia::render(...)`,
  `RedirectResponse`, or `Response`. They use the `ValidatesWebRequests`
  trait and the core `Resolver::resolveValidator(...)` flow.
- **[API]** JSON:API controllers live in
  `app/Http/Controllers/Api/...` and extend
  `Thinkycz\LaravelCore\Routing\AutomaticController`. They use the
  `ApiFormRequest` builder and the `Typer` parser for typed input.

### **[Web]** Inertia Conventions

- Use Inertia `<Link>` for all internal navigation; never raw
  `<a href>`. Internal links that need button styling should be a
  `<Link>` whose content is a `<Button>`-styled element, or
  `as="button"` where supported.
- Use Inertia `<Form>` for ordinary forms. Use `router.get(...)` for
  searches and explicit navigation, preserving state where appropriate.
  Never navigate by assigning `window.location.href`.
- Use `Inertia::flash('success', \__('...'))` for success flashes; the
  `HandleInertiaRequests` middleware also falls back to session
  `->flash()`, but `Inertia::flash()` survives 302 chains.
- `<Form>` owns submission, processing and validation state; provide its
  `action` and `method` props. Custom native forms need `@submit.prevent`
  and an explicit router call. Standard Laravel redirects and session
  errors are canonical; do not map request paths to Inertia components.
- Inertia pages live in `resources/js/pages/` and are resolved by
  `resources/js/app.ts`. Use `@/` for `resources/js` imports.
- Inertia v3 stores flash data on the dedicated
  `inertia.flash_data` session key; on a 200-OK render the message
  surfaces in the `flash.{key}` page prop.

### **[API]** JSON:API Conventions

- Resource controllers are invokable.
- The `Store` (create) action returns a generic `ModelJsonApiResource`,
  not an `Index`/`Show` resource. Updates and destroys return
  `204 No Content`.
- Output is always `application/json`.
- All persistence happens inside a transaction; the
  `AutomaticController` base handles this automatically.

## URL Format

- **[Web]** URLs in `kebab-case`: `/stock-movements`, `/items`,
  `/stores`. Routes use the resource id as a path segment:
  `/items/{item}`. This is an intentional exception to the API rule
  below.
- **[API]** URLs in `snake_case` plural: `/api/v1/email_verification/resend`.

## Flat Endpoints (API only)

- **[API]** Endpoints are flat with no nested relations.
    - Right: `GET /api/v1/notifications/index?filter[user_id]=1`
    - Wrong: `GET /api/v1/users/1/notifications`
- **[API]** Don't use route params, use query params:
  `GET /api/v1/users/show?id=1`.
- **[API]** Only `GET` and `POST` methods. Replace `PUT`/`PATCH`/`DELETE`
  with `POST` and a postfix: `POST /api/v1/users/update?id=1`.

## Basic Endpoint Structure (API only)

- Index: `GET /api/v1/{models}/index`
- Show: `GET /api/v1/{models}/show?id=number`
- Store: `POST /api/v1/{models}/store`
- Update: `POST /api/v1/{models}/update?id=number`
- Destroy: `POST /api/v1/{models}/destroy?id=number`
- Attach/Detach: `POST /api/v1/{a_model_b_model}/store|destroy`

## Naming Conventions

- Classes have type suffixes: `Controller`, `Request`, `Resource`,
  `Service`, `Validity`.
- Models exclude `Model` suffix (`Item.php`, not `ItemModel.php`).
- Traits have `Trait` suffix. Interfaces have `Interface` suffix.
  Abstract classes have `Abstract` prefix. Final classes have `Final`
  prefix. Enums have `Enum` suffix.
- Class names are singular: `UserStoreController.php`,
  `UserRoleEnum.php`.

## Tables, Columns, Foreign Keys

- Tables plural `snake_case`. Pivot tables singular alphabetical
  `snake_case`. Columns `snake_case`. Foreign keys end in `_id`.

## PSR Standards

- Follow PSR-1, PSR-2, PSR-4, PSR-12, PSR-5, PSR-19.

## Translation Files

- `resources/js/i18n/{en,cs,sk}.json` for frontend (vue-i18n).
- `lang/{en,cs,sk}.json` for backend (`__()`) — includes email subjects
  and one-off strings.
- Three locales must remain in sync. Add a key to all three at the
  same time. The `tests/Unit/I18nParityTest.php` test enforces parity
  on every CI run.
- Keys in `snake_case` (nested OK).
- Hardcoded user-facing strings in PHP or Vue are a code smell — move
  them to i18n.

## Controllers, Authorization, Validation

- Authorization and validation in the controller, not in request
  classes.
- **[Web]** Validation rules live in
  `Thinkycz\LaravelCore\Validation\AuthValidity` (and any project-
  specific `*Validity` classes that extend it). Inject with
  `*Validity::inject()` and use `$this->validateRequest($request,
$rules)` (from `App\Http\Controllers\Web\Concerns\ValidatesWebRequests`)
  to obtain a typed `Parser` for `assertString/assertNullableInt/...`.
- **[API]** Validation rules use the same `AuthValidity` helpers; the
  API controller passes them to the `ApiFormRequest::builder()`.
- Multi-step writes (`create + related`, `update + tokens`,
  `password + revoke`) must run in `DB::transaction(...)`.
- All queries must be scoped to the logged-in user via explicit
  ownership checks (e.g. `ConversationRepository::findOwned($id,
$user)`) or Eloquent relationship methods that take the user as
  a parameter. There is no global `->forUser()` scope — call out
  ownership at the call site so the intent is obvious.
- Forbids `ValidationException::withMessages(...)`. Use
  `Thrower::default()->message($key, $message)->throw()`.
- Forbids `env()`, `config()`, `dd()`, `var_dump()`, `print_r()`,
  `unserialize()`, `extract()` in `app/`. `app()->environment()`,
  `Auth::shouldUse()`, `Password::getConfig()`, etc. are also
  forbidden; use the core's typed helpers instead.

## Dependency Injection

- **[Web]** Don't use constructor or method DI. Resolve via
  `Resolver::resolve*()` in the method body.
- **[API]** `AutomaticController` injects via constructor; services
  used inside action methods can be resolved via `Resolver` or method
  DI.

## Contracts

- Imported classes from `Illuminate\Contracts` should have a `Contract`
  suffix: `AuthenticatableContract`. (This is a soft preference for new
  code; the core package itself doesn't always follow it.)

## Down Migration

- Skip `down()` in migrations.

## Comments

- Every property and every method must have a docblock comment.
- Do not use phpdocblocks that affect functionality at runtime
  (opcache strips them).

## Throttling

- Throttle at the controller level after validation. Use the
  `ThrottlesWebRequests` trait for the web surface.
- Failed validation must not increase throttle hits.
- Set `E2E_DISABLE_THROTTLE=true` in the test environment to bypass
  throttling during Playwright runs (the `ThrottlesWebRequests` trait
  short-circuits `hit()` to a no-op when this env is on).

## ID Getter

- Access model IDs only via `getKey()`, `getAuthIdentifier()`,
  `getRouteKey()`. Never read `$model->id` directly.

## Mail Queue

- Emails and notifications must implement `ShouldQueue` and be sent
  after the database transaction commits.

## Working with Logged-in User

- Use `Model::mustAuth(): User` (throws 401) and
  `Model::auth(): User|null` from the core's base models.

## Inheritdoc

- Overridden methods must inherit phpdoc with `@inheritDoc`.

## Cron Schedule

- Define cron tasks using Jobs, not Artisan commands.

## Single Job

- Jobs processing collections must use recursive processing: fetch
  the model, dispatch a single-model job for each within a transaction.

## CRUD Command

- Generate boilerplate code exclusively with CRUD scaffolding commands
  shipped by the `thinkycz/laravel-core` package.

## Authentication and AI execution contracts

- Share cookie encryption and sessions across web/API. Cookie-authenticated
  mutations, including login/registration, require CSRF protection. Initialize
  API cookies via `GET /api/v1/auth/csrf`; exempt only valid explicit bearer
  authentication. CORS uses an explicit trusted-origin list, never wildcard
  credentialed access. Secure host-only cookies outside local/testing must
  satisfy `__Host-` attributes.
- Send password-reset links, never raw passwords. Reset requests leave credentials
  unchanged; completion atomically consumes the token and revokes account tokens.
  Email changes clear verification; successful verification consumes its token.
- Keep failed-login budgets separate from operation quotas. Count validated
  successful AI starts (30/minute/user). Limit reset/verification delivery to
  one message/minute/address and a separate IP abuse budget.
- The selected AI architecture uses official SDK `RemembersConversations` as
  the sole message-persistence owner. `User` keeps `HasConversations`, and
  ownership is scoped through its polymorphic participant relation.
- Stream `POST /agent/chat` using the official Vercel protocol and `@ai-sdk/vue`.
  Adopt `x-conversation-id` as canonical. Do not restore custom run/event tables,
  assistant placeholders, queued provider jobs or replay cursors.
- Approvable notification tools pause for official approve/edit/reject decisions.
  Resumption continues the pending tool call; it is not a clarification next
  turn. Preserve SDK approval state and validate decision IDs/arguments.
- Keep ownership checks, concurrent-request protection, safe errors and deletion
  cleanup around the SDK boundary. Approval-state persistence is not proof of
  exactly-once external effects. Test the real approval loop with fake transport.
- Preserve the 180-second request lease and `SafeChatResponse` sanitization,
  event-boundary deadline/disconnection/deletion checks, and null-reason protocol
  adaptation. Bind tools to the explicit conversation participant.
- Queue workers deliver notifications, not chat streams. Treat browser abort as
  a transport operation, not an acknowledged server terminal-state transition.
  Do not promise partial-response persistence or durable reconnect recovery.
- Keep provider context and UI history bounds distinct. Measure SDK streaming
  writes afresh; the old run/event SQL benchmark is no longer applicable.
- Generated CRUD lookups scope ownership before fetching. Pagination defaults to
  25 with a maximum of 100. Validate generated source and behavior in a disposable
  application; never generate test fixtures into the working application's code.

## Safe verification and reproducible installs

- Commit Composer and npm lockfiles. Use `composer install` and `npm ci` for
  reproducible installs; dependency updates must deliberately refresh lockfiles.
- Select isolated testing configuration before bootstrap. Never clear developer
  application caches as a test prerequisite or run `migrate:fresh` against the
  current environment. Browser tests own a temporary database/server and fake
  providers; do not substitute an arbitrary already-running server.
- Build assets before pruning build dependencies and require the Vite manifest.
  Verify deployment sequences only in disposable checkouts.
- Keep PHPStan at max with types certain. Correct callback/model contracts or
  use precise runtime assertions; no baseline, broad ignores or suppressions.
- Run targeted tests during changes, then `make fix`, `make check` and `make e2e`
  for integration. Report separate MySQL/Redis and live-provider/deployment gaps.
