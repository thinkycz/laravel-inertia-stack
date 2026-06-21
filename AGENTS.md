# AGENTS.md

## Purpose and scope

- This file captures project-specific architecture, workflows, and coding rules.
- `docs/guidelines.md` remains authoritative where it applies.

## Stack and runtime

- Laravel 13 on PHP 8.3.
- Inertia 3, Vue 3 Composition API, TypeScript, Tailwind 4, Vite.
- Local package dependency: `packages/thinkycz/laravel-core`.
- Authentication uses the core `database_token` guard and HTTP-only cookies.
- Runtime services: MySQL 8, Redis, cron, supervisor.

## Repo layout and key files

- `app/Http/Controllers/Web/` contains Inertia page and form controllers.
- `app/Http/Controllers/Api/` keeps minimal API-compatible auth endpoints.
- `app/Http/Middleware/HandleInertiaRequests.php` shares app/auth/flash props.
- `resources/js/` follows the Laravel Vue starter-kit shape:
    - `components/`, `composables/`, `layouts/`, `lib/`, `pages/`, `types/`.
- `routes/web.php` is the primary UI surface.
- `routes/api.php` is retained only for auth/me/password/email compatibility.
- `packages/thinkycz/laravel-core/` contains reusable framework helpers, guards, validation, and scaffolding stubs.

## Tooling and workflows

- Makefile is the primary workflow entry:
    - Provisioning: `make local|testing|development|staging|production`.
    - Formatting: `make fix`.
    - Validation: `make check` runs PHPStan, Prettier/Pint, audits, frontend build/type-check, Vitest unit tests, and PHPUnit tests.
- Before each commit: run `make fix` then `make check`.
- PHPStan must remain at `level: max` with `treatPhpDocTypesAsCertain: true`; do not lower strictness, reintroduce a baseline, or add broad ignores to make analysis pass. Eloquent's magic builder chains are exempt from strict-rules' `dynamicCallOnStaticMethod` warning (see `phpstan.neon`).
- Frontend checks are `npm run type-check` and `npm run build`.

## Backend conventions

- Keep app-level behavior thin and delegate framework behavior to `thinkycz/laravel-core`.
- Import all PHP class/interface/trait/enum names with `use` statements. Do not write inline fully qualified class names in signatures, route definitions, PHPDoc, catches, callbacks, or method bodies when the symbol can be imported.
- Do not add model `@property`, `@method`, or `@phpstan-method` PHPDoc to make dynamic Eloquent access pass. Persisted attributes must be read through explicit getters that use `assertString`, `assertInt`, `assertNullableString`, `Typer::*`, or the closest precise assertion.
- Relations must be accessed through explicit relationship methods for queries or through typed relation getters such as `getStore()` / `getMovementItems()`. Do not read `$model->relation` properties in application code.
- Call local Eloquent scopes directly, for example `Item::scopeSearch($query, $search)` or inside `tap()` with an explicit static scope call. Do not rely on magic builder methods such as `$query->search()` or `$query->forUser()`.
- PHPDoc is still allowed for real generic contracts such as relationship return types, `@param Builder<Model>`, and `@use HasFactory<Factory>`.
- Avoid single-use temporary variables for obvious expressions. Inline trivial values such as `'%' . $search . '%'` and remove unused locals immediately.
- Use `Thinkycz\LaravelCore\Support\Resolver` for framework helpers when following existing core patterns.
- Use validity classes such as `AuthValidity` for validation rules.
- Work with the logged-in user using `User::auth()` and `User::mustAuth()`.
- API controllers may use `Thinkycz\LaravelCore\Http\ApiFormRequest`; Inertia web controllers should use standard Laravel redirects and validation errors.
- Use `Thrower::default()->message('field', Typer::assertString(\__('...')))->throw()` instead of `ValidationException::withMessages(...)`.
- Use `Inertia::flash('success', \__('...'))` instead of `$request->session()->flash(...)` so messages survive 302 → guest-redirect chains.
- Wrap multi-step persistence (e.g. `update + remember_token + tokens`, `password + revoke`) in `DB::transaction(...)`.
- DB writes should stay transactional when multi-step persistence is introduced.
- Code must pass PHPStan without `phpstan-baseline.neon`; fix the underlying type issue instead of suppressing it.
- Never call `env()` or `\env()` directly, including in config files. Read environment values through `$env = Env::inject();` and the appropriate typed parser/assertion method.

## Frontend conventions

- Vue pages live in `resources/js/pages` and are resolved by `resources/js/app.ts`.
- Use `@/` for `resources/js` imports.
- Prefer small app UI components under `resources/js/components/ui`.
- TypeScript must reject unused locals and parameters. Keep `noUnusedLocals` and `noUnusedParameters` enabled, and remove confirmed unused imports, locals, and dependencies.
- Do not introduce a marketing landing page as the default screen; the first useful screen is the auth/dashboard workflow.
- Keep UI restrained, responsive, and task-focused.

## Scope notes

- The old reference catalog/order sample domain is intentionally omitted.
- OpenAPI demo routes and runtime generation are intentionally omitted from the default workflow.
- SSR is intentionally deferred; add it later through `@inertiajs/vite` if a project needs it.

===

===

<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to ensure the best experience when building Laravel applications.

## Foundational Context

This application is a Laravel application and its main Laravel ecosystems package & versions are below. You are an expert with them all. Ensure you abide by these specific packages & versions.

- php - 8.3
- inertiajs/inertia-laravel (INERTIA_LARAVEL) - v3
- laravel/ai (AI) - v0
- laravel/framework (LARAVEL) - v13
- laravel/prompts (PROMPTS) - v0
- larastan/larastan (LARASTAN) - v3
- laravel/boost (BOOST) - v2
- laravel/mcp (MCP) - v0
- laravel/pail (PAIL) - v1
- laravel/pint (PINT) - v1
- laravel/sail (SAIL) - v1
- pestphp/pest (PEST) - v4
- phpunit/phpunit (PHPUNIT) - v12
- @inertiajs/vue3 (INERTIA_VUE) - v3
- vue (VUE) - v3
- prettier (PRETTIER) - v3
- tailwindcss (TAILWINDCSS) - v4

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If the user doesn't see a frontend change reflected in the UI, it could mean they need to run `npm run build`, `npm run dev`, or `composer run dev`. Ask them.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

## Replies

- Be concise in your explanations - focus on what's important rather than explaining obvious details.

=== boost rules ===

# Laravel Boost

## Tools

- Laravel Boost is an MCP server with tools designed specifically for this application. Prefer Boost tools over manual alternatives like shell commands or file reads.
- Use `database-query` to run read-only queries against the database instead of writing raw SQL in tinker.
- Use `database-schema` to inspect table structure before writing migrations or models.
- Use `get-absolute-url` to resolve the correct scheme, domain, and port for project URLs. Always use this before sharing a URL with the user.
- Use `browser-logs` to read browser logs, errors, and exceptions. Only recent logs are useful, ignore old entries.

## Searching Documentation (IMPORTANT)

- Always use `search-docs` before making code changes. Do not skip this step. It returns version-specific docs based on installed packages automatically.
- Pass a `packages` array to scope results when you know which packages are relevant.
- Use multiple broad, topic-based queries: `['rate limiting', 'routing rate limiting', 'routing']`. Expect the most relevant results first.
- Do not add package names to queries because package info is already shared. Use `test resource table`, not `filament 4 test resource table`.

### Search Syntax

1. Use words for auto-stemmed AND logic: `rate limit` matches both "rate" AND "limit".
2. Use `"quoted phrases"` for exact position matching: `"infinite scroll"` requires adjacent words in order.
3. Combine words and phrases for mixed queries: `middleware "rate limit"`.
4. Use multiple queries for OR logic: `queries=["authentication", "middleware"]`.

## Artisan

- Run Artisan commands directly via the command line (e.g., `php artisan route:list`). Use `php artisan list` to discover available commands and `php artisan [command] --help` to check parameters.
- Inspect routes with `php artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `php artisan config:show app.name`, `php artisan config:show database.default`. Or read config files directly from the `config/` directory.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `php artisan tinker --execute 'Your::code();'`
    - Double quotes for PHP strings inside: `php artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Follow existing application Enum naming conventions.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== deployments rules ===

# Deployment

- Laravel can be deployed using [Laravel Cloud](https://cloud.laravel.com/), which is the fastest way to deploy and scale production Laravel applications.

=== tests rules ===

# Test Enforcement

- Every change must be programmatically tested. Write a new test or update an existing test, then run the affected tests to make sure they pass.
- Run the minimum number of tests needed to ensure code quality and speed. Use `php artisan test --compact` with a specific filename or filter.

=== inertia-laravel/core rules ===

# Inertia

- Inertia creates fully client-side rendered SPAs without modern SPA complexity, leveraging existing server-side patterns.
- Components live in `resources/js/pages` (unless specified in `vite.config.js`). Use `Inertia::render()` for server-side routing instead of Blade views.
- ALWAYS use `search-docs` tool for version-specific Inertia documentation and updated code examples.
- IMPORTANT: Activate `inertia-vue-development` when working with Inertia Vue client-side patterns.

# Inertia v3

- Use all Inertia features from v1, v2, and v3. Check the documentation before making changes to ensure the correct approach.
- New v3 features: standalone HTTP requests (`useHttp` hook), optimistic updates with automatic rollback, layout props (`useLayoutProps` hook), instant visits, simplified SSR via `@inertiajs/vite` plugin, custom exception handling for error pages.
- Carried over from v2: deferred props, infinite scroll, merging props, polling, prefetching, once props, flash data.
- When using deferred props, add an empty state with a pulsing or animated skeleton.
- Axios has been removed. Use the built-in XHR client with interceptors, or install Axios separately if needed.
- `Inertia::lazy()` / `LazyProp` has been removed. Use `Inertia::optional()` instead.
- Prop types (`Inertia::optional()`, `Inertia::defer()`, `Inertia::merge()`) work inside nested arrays with dot-notation paths.
- SSR works automatically in Vite dev mode with `@inertiajs/vite` - no separate Node.js server needed during development.
- Event renames: `invalid` is now `httpException`, `exception` is now `networkError`.
- `router.cancel()` replaced by `router.cancelAll()`.
- The `future` configuration namespace has been removed - all v2 future options are now always enabled.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `php artisan list` and check their parameters with `php artisan [command] --help`.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `php artisan make:model --help` to check the available options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

## Vite Error

- If you receive an "Illuminate\Foundation\ViteException: Unable to locate file in Vite manifest" error, you can run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== pest/core rules ===

## Pest

- This project uses Pest for testing. Create tests: `php artisan make:test --pest {name}`.
- The `{name}` argument should not include the test suite directory. Use `php artisan make:test --pest SomeFeatureTest` instead of `php artisan make:test --pest Feature/SomeFeatureTest`.
- Run tests: `php artisan test --compact` or filter: `php artisan test --compact --filter=testName`.
- Do NOT delete tests without approval.

=== inertia-vue/core rules ===

# Inertia + Vue

Vue components must have a single root element.

- IMPORTANT: Activate `inertia-vue-development` when working with Inertia Vue client-side patterns.

</laravel-boost-guidelines>

<laravel-inertia-stack-overrides>
=== PROJECT CONVENTIONS OVERRIDE THE BOOST RULES ABOVE ===

This project is a **hybrid Inertia-web + JSON:API** app. The project rules in `AGENTS.md` (before `<laravel-boost-guidelines>`) and `docs/guidelines.md` take precedence over the Boost defaults above. The most important conflicts to watch for:

## Two surfaces, two controller patterns

| Surface   | Path                     | Controller style                                                                          | Output                                                 |
| --------- | ------------------------ | ----------------------------------------------------------------------------------------- | ------------------------------------------------------ |
| **[Web]** | `/...` (Inertia)         | `create/store` and `edit/update` named-method pairs in one controller (NOT invokable)     | `Inertia::render(...)`, `RedirectResponse`, `Response` |
| **[API]** | `/api/v1/...` (JSON:API) | One invokable controller per action (`MeShowController`, `PasswordForgotController`, ...) | `JsonApiResource`, `204 No Content`                    |

- Web controllers live in `app/Http/Controllers/Web/...` and use the `ValidatesWebRequests` trait (provides `validateRequest(...)` and `validateWeb(...)`).
- API controllers live in `app/Http/Controllers/Api/...` and extend `Thinkycz\LaravelCore\Routing\AutomaticController`, with `__invoke(ApiFormRequest $request): SymfonyResponse`.
- Both use `Thinkycz\LaravelCore\Validation\AuthValidity` and `*Validity` classes that extend it. Inject with `*Validity::inject()`. Validity classes only declare wrapper helpers — no `->required()` / `->nullable()` chains; those live in the controller.
- Every controller (except `Concerns/`) MUST have a matching `*ControllerTest.php` under `tests/Feature/.../`. Enforced by `CoverageArchitectureTest`.

## URLs and routing

- **[Web]** URLs in `kebab-case` with route params: `/items/{item}`, `/stock-movements`, `/stores`. This is an intentional exception to the API rule below.
- **[API]** URLs in `snake_case` plural, flat, no nested relations: `/api/v1/notifications/index?filter[user_id]=1`.
- **[API]** No route parameters. Use query params: `GET /api/v1/users/show?id=1` (not `GET /api/v1/users/{id}`).
- **[API]** Only `GET` and `POST` are used. Replace `PUT`/`PATCH`/`DELETE` with `POST` + postfix: `POST /api/v1/users/update?id=1`.
- All routes are registered through `Resolver::resolveRouteRegistrar()`; do not use raw `Route::*()`.

## Inertia web conventions

- Use Inertia `<Link>` for all internal navigation; never raw `<a href>`. Internal links that need button styling should be a `<Link>` whose content is a `<Button>`-styled element, or `as="button"` where supported.
- Use `router.get/post/...` for all form submissions and searches; never `window.location.href`. Preserve state on search/filter navigation with `router.get(url, params, { preserveState: true })`.
- Use `Inertia::flash('success', \__('...'))` for success flashes — NOT `$request->session()->flash(...)`. `Inertia::flash()` survives 302 chains.
- For form submits, bind `@submit.prevent` and call `router.post(...)`; do not set redundant `method`/`action` attributes on the `<form>` element.
- Pages live in `resources/js/pages/` and are resolved by `resources/js/app.ts`. Use `@/` for `resources/js` imports.
- Vue components must have a single root element.

## Errors and validation

- Forbids `ValidationException::withMessages(...)`. Use `Thrower::default()->message($key, $message)->throw()` instead.
- Forbids `env()`, `config()`, `dd()`, `var_dump()`, `print_r()`, `unserialize()`, `extract()` everywhere in `app/`. `app()->environment()`, `Auth::shouldUse()`, `Password::getConfig()` are also forbidden — use the core's typed helpers (`Env::inject()`, `Config::inject()`, `Resolver::resolve*()`).
- Throttle at the controller level AFTER validation (use `ThrottlesWebRequests` trait for web). Failed validation must NOT count toward throttle hits.
- Set `E2E_DISABLE_THROTTLE=true` in the test environment to bypass throttling during Playwright runs.

## Docblocks and PHPDoc

- **Do not use model `@property`, `@method`, or `@phpstan-method` PHPDoc** as a substitute for real APIs. Persisted attributes must be read through explicit getters (`assertString`, `assertInt`, `assertNullableString`, `Typer::*`).
- Relations are accessed through explicit relationship methods for query building, or through typed relation getters (`getStore()`, `getMovementItems()`). Application code does NOT read `$model->relation` properties.
- Local Eloquent scopes are called explicitly: `Item::scopeSearch($query, $search)`, or inside `tap()` with a static scope call. Do NOT rely on magic builder methods like `$query->search()` or `$query->forUser()`.
- PHPDoc remains appropriate for real generic contracts: relationship return types, `@param Builder<Model>`, `@use HasFactory<Factory>`. The Boost `php rules` advice to "Prefer PHPDoc blocks over inline comments" and "Use array shape type definitions in PHPDoc blocks" does **NOT** apply here.
- Every property, method, function must have a docblock comment. Overrides use `@inheritDoc`. PHPStan runs at `level: max` with `treatPhpDocTypesAsCertain: true` and passes without `phpstan-baseline.neon`.

## Dependency injection

- **[Web]** No constructor or method DI. Resolve via `Resolver::resolve*()` in the method body.
- **[API]** `AutomaticController` injects via constructor; services inside action methods can be resolved via `Resolver` or method DI.
- Use `User::auth(): User|null` and `User::mustAuth(): User` (throws 401) from the core's base models. Do NOT use a generic `Model::resolve()`.

## CRUD scaffolding

- Generate boilerplate code exclusively with the CRUD scaffolding commands shipped by the `thinkycz/laravel-core` package (see `packages/thinkycz/laravel-core/src/Providers/CoreServiceProvider.php`).
- The Boost `laravel/core rules` advice to "Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.)" does NOT apply — use the core package's `make:crud`, `make:model -mf`, `make:validity`, `make:crud-index|show|store|update|destroy`, `make:crud-routes`, `make:test-*`, `make:enum` commands.

## Transactions, throttling, IDs

- Multi-step writes (`create + related`, `update + tokens`, `password + revoke`) MUST run in `DB::transaction(...)`.
- Model IDs are accessed via `getKey()`, `getAuthIdentifier()`, `getRouteKey()`. Never read raw `$model->id`.
- All queries must be scoped to the logged-in user via explicit ownership checks (`ConversationRepository::findOwned($id, $user)`) or Eloquent relationship methods that take the user as a parameter. There is NO global `->forUser()` scope — call out ownership at the call site.

## Naming

- Classes have type suffixes: `Controller`, `Request`, `Resource`, `Service`, `Validity`. Models exclude `Model` suffix. Traits have `Trait` suffix. Interfaces have `Interface` suffix. Abstract classes have `Abstract` prefix. Final classes have `Final` prefix. Enums have `Enum` suffix. Class names are singular.
- Tables: plural `snake_case`. Pivot tables: singular alphabetical `snake_case`. Columns: `snake_case`. Foreign keys end in `_id`.

## i18n — three locales must stay in sync

- Backend translations: `lang/{en,cs,sk}.json` for `__()` calls (includes email subjects and one-off strings).
- Frontend translations: `resources/js/i18n/{en,cs,sk}.json` for `vue-i18n`.
- **Three locales must remain in sync.** Add a key to all three at the same time. The `tests/Unit/I18nParityTest.php` test enforces parity on every CI run.
- Keys in `snake_case` (nested OK). Hardcoded user-facing strings in PHP or Vue are a code smell — move them to i18n.

## Mail, jobs, cron

- Emails and notifications must implement `ShouldQueue` and be sent only after the DB transaction commits.
- Cron tasks are defined as Jobs, not Artisan commands.
- Jobs processing collections use recursive processing: fetch the model, dispatch a single-model job for each within a transaction.

## Frontend conventions

- Vue 3 Composition API, TypeScript, Tailwind 4, Vite, Inertia 3.
- `@/` resolves to `resources/js`. Small UI primitives go under `resources/js/components/ui`.
- TypeScript must keep `noUnusedLocals` and `noUnusedParameters` enabled. Remove confirmed unused imports, locals, and dependencies instead of leaving dead frontend code.
- Do not introduce a marketing landing page as the default screen; the first useful screen is the auth/dashboard workflow.
- Keep UI restrained, responsive, and task-focused.

## Tests

- Pest is the test framework. Test files mirror the source tree: `app/Http/Controllers/Web/Auth/LoginController.php` → `tests/Feature/App/Http/Controllers/Web/Auth/LoginControllerTest.php`.
- 100% coverage of the success path is expected; error path coverage is recommended but not mandatory.
- Use the `UserFactory` to create users in tests. Use the `assertInertiaFlash(TestResponse $response, string $key, mixed $message)` helper for Inertia flash assertions.
- Replace `Typer::assertInstance($x, X::class)` with `expect($x)->toBeInstanceOf(X::class)`.
- Architecture tests live in `tests/Architecture/*` and enforce the rules above. Do NOT lower strictness, reintroduce a baseline, add broad ignores, or suppress errors in code.

</laravel-inertia-stack-overrides>
