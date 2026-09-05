# Lessons learned

Durable notes for future agents working on this codebase. Each entry
records a problem we hit, why it surprised us, the signal that would
catch it next time, and the fix.

## Inertia v3

### Bare 302 redirects are silently dropped by the client

**Problem.** A web controller returns `redirect()->to('/x')->with('success', '...')`.
On Inertia v3, the form submit does not navigate, the flash never
shows on `/x`, and the user sees no feedback.

**Why it surprised us.** Laravel's default `back()->withErrors()` and
`->with()` patterns are 302 + session flash. They work for classic
Laravel redirects. Inertia v3 is stricter: the v3 client only
follows **409** with **`X-Inertia-Redirect`** (internal
`router.visit()`) or **`X-Inertia-Location`** (external
`window.location.href`). A bare 302 hits `handleNonInertiaResponse`
in `node_modules/@inertiajs/core/dist/index.js` and falls through to
an error dialog.

**Signal.** Anything that ends with `->with('key', 'value')` and
returns a `Symfony\Component\HttpFoundation\Response` to a route
that an Inertia form can post to.

**Fix.** For same-request flashes: `session()->flash(...)` +
`Inertia::render(...)` of the same component. For flashes that
must survive a 302 chain: `Inertia::flash(...)` + `redirect(...)`.
See `app/Http/Controllers/Web/Settings/ProfileController.php` and
`app/Http/Controllers/Web/Auth/EmailVerificationConfirmController.php`
respectively.

### `Inertia::flash()` survives redirect chains; `session()->flash()` does not

**Problem.** The `EmailVerificationConfirmController` redirects to
`/login` for an invalid token. The user is already authenticated, so
`/login` then redirects to `/dashboard`. The success/error flash
needs to reach the dashboard.

**Why it surprised us.** A session flash is supposed to be available
"on the next request". With three sequential requests (the original
302, the guest-redirect 302, the final 200), it ages out.

**Mechanism.** Session middleware calls `ageFlashData()` at the start
of every request: it moves `_flash.new` to `_flash.old`, then drops
`_flash.old` at the next start. So a flash survives exactly one
request, not three.

**Why `Inertia::flash()` works.** It stores under a single key
`inertia.flash_data` (see
`vendor/inertiajs/inertia-laravel/src/Support/SessionKey.php`).
`vendor/inertiajs/inertia-laravel/src/Middleware.php::reflash()` is
called after every redirect response: it reads the key, then
re-flashes it so it lands in `_flash.new` again. Net effect: the
flash survives as many redirect hops as needed.

**Signal.** Cross-request flash that needs to land on a page the
controller does not directly render.

**Fix.** `Inertia::flash('success', $message); return
redirect('/dashboard')`. The middleware's reflash does the rest.
`HandleInertiaRequests::share()` reads `Inertia::getFlashed($request)`
first and falls back to `$request->session()->get($key)` for
same-request controllers.

### `Inertia\Response::with('success', '...')` is a prop, not a flash

**Problem.** First attempt at the verifyEmail success message used
`return Inertia::render('page', [])->with('success', $message)`. The
`Alert` never appeared.

**Why it surprised us.** `redirect()->with()` is a session flash in
classic Laravel. We assumed `Response::with()` was the same idea on
the Inertia side. It is not: it pushes the value into
`$this->props[$key] = $value`, where it lives only inside the
current response. It does not become part of `useSharedProps().flash`
on the next page.

**Signal.** A flash that "should be there" but isn't, and the
controller used `Inertia::render(...)->with('key', ...)`.

**Fix.** Use `Inertia::flash()` for cross-request, or
`Inertia::flash()` before rendering or redirecting.

### Validation errors follow the redirect and session contract

The previous custom 422 handler mapped paths to components, including nonexistent
settings components, and lost the submitting form. That approach was based on an
incorrect assumption about Inertia. Use Laravel validation redirects and shared
session errors with Inertia `<Form>`. Do not infer a page component from a POST
URL or partial-reload headers. Keep JSON API validation responses separate.

### The HTML body of the Inertia `data-page` script is the source of truth

**Problem.** A test that relies on `page.goto(...)` returns the
flash only if it was embedded in the bootstrap HTML, not the
follow-up Inertia XHR. Easy to assert on the wrong response.

**Why it surprised us.** After `page.goto()`, the URL is the
final one and the Inertia client may or may not make an XHR
depending on the page lifecycle.

**Fix.** For initial-render flashes, read from
`document.querySelector('script[data-page="app"]').textContent` and
parse the embedded JSON. For follow-up navigations, intercept
the XHR.

## PHPStan in this project

### Correct types instead of suppressing PHPStan

Use `Typer::assertString(\__('...'))` when a consumer requires a translated
string. Widening parameters to `mixed` solely to silence analysis discards the
contract. Relationship query callbacks receive the related model builder, not the
owner model builder. An empty-array assertion returns `array{}`, not an arbitrary
input generic. These precise contracts remove suppressions without weakening max
analysis or type certainty.

### No `assertString()` on `AuthValidity`; use `varchar()`

**Problem.** We tried `$authValidity->assertString()->required()`.
`AuthValidity` exposes `email()`, `password()`, `emailVerification-
Token()`, `locale()`, etc. — but no `assertString()`. The trait
`HasValidityStringRules` provides `varchar(int|null $max, int|null
$min)` for raw strings.

**Signal.** "I just need a required string, why is this hard."

**Fix.** For typed strings, use the named helper (e.g. `email()`).
For raw required strings, use
`$authValidity->baseValidity->make()->varchar()->required()->toArray()`.

### `instanceof MustVerifyEmail` is dead code when `User implements MustVerifyEmail`

**Problem.** PHPStan flagged
`if ($user instanceof MustVerifyEmail === false)` as
`instanceof.alwaysTrue`.

**Signal.** Adding a defensive check that the type system already
proves.

**Fix.** Drop the check. `app/Models/User.php:13` declares
`implements MustVerifyEmail` directly, so the runtime check is
unreachable.

## Test ergonomics

### `Notification::fake` hides missing-translation 500s

**Problem.** `EmailVerificationNotification` calls
`Trans::inject()->assertString('spa.email_verification_url')` to
build the verification URL. The translation was missing. PHPUnit
passed (because `Notification::fake()` short-circuits the
notification). E2E 500'd on the real render.

**Why it surprised us.** Unit tests are usually stricter than e2e.
Here the unit test was lenient because the fake is
indistinguishable from "no notification was sent".

**Signal.** Whenever `Notification::fake()` is in a phpunit test
that exercises code which calls `notify(...)`, the real
notification render is never run.

**Fix.** For any notification whose render reads from
`Trans::inject()` or templates, add a phpunit test that
dispatches the notification through a `Notification::fake()`-aware
spy **or** an e2e that triggers the real send. We added the e2e.

### Test browser behavior and server behavior at their own boundaries

Pest can check validation/session and flash contracts precisely. Browser tests
must still exercise the resulting navigation and visible form errors. A passing
server test does not justify removing browser coverage of an important user
journey. Use the isolated real test server; avoid endpoint mocks that no longer
match current routes.

## Dev environment

### Local domain resolution with Laravel Valet or Herd

**Context.** When developing locally, there is a high chance that the developer is using Laravel Valet or Herd, so the project runs automatically on a `.test` domain (for example, `http://laravel-inertia-stack.test`).

**Fix.** If the `.test` domain does not resolve or work, run a local development web server manually using `php artisan serve`.

### Own test processes and artifacts explicitly

The browser runner owns a fixed server port, temporary SQLite database, sessions,
cache and child worker processes. It refuses to reuse another server. Cleanup
must stop only processes it started and remove only its temporary directory.
Broad `pkill` commands can terminate unrelated developer work. A busy fixed port
is a test startup failure, not permission to connect to a different application.

### Test cookie attributes deliberately

Local/testing cookies omit the production `__Host-` prefix. Outside those
environments, cookies are secure, host-only and use path `/`, which satisfies the
prefix contract. Tests must assert attributes directly and verify shared web/API
authentication; browser localhost exceptions are not evidence that deployment
cookie settings are correct.

### Keep test diagnostics inside the test-owned workspace

Inspect the browser runner and worker logs from the test's temporary directory.
If more logging is needed, configure the disposable environment or use existing
test assertions. Do not insert hardcoded `/tmp` writes into application request
handlers or depend on developer logging configuration.

## Workflow

### Inspect check commands before running them

Make is the normal workflow entry point. Targeted Pest, Vitest and TypeScript
commands are appropriate during development. `make check` covers static analysis,
formatting, dependency audits, build and PHP/frontend unit tests; `make e2e` is a
separate browser check. Never infer isolation from a command name: inspect its
bootstrap and database selection first. Tests must not clear developer caches.

### Run `make fix` then `make check` before every commit

**Why.** Pint reorders class elements, alphabetizes imports, and
rewrites native function calls. PHPStan catches type errors that
are invisible at runtime. Prettier aligns the frontend. Audits
flag supply-chain issues. Without the loop, the next `make check`
on CI fails on a fixable lint issue.

## SDK conversation and approval lessons

A single persistence owner still matters. The earlier audit found duplication
because application writes and SDK remembering ran together. The selected
remote-main architecture keeps official `RemembersConversations` and removes the
application message writer. Do not restore both while adapting audit fixes.

Official approvals are not clarification questions. Approve/edit/reject resumes
a pending SDK tool call. Persisted tool parts and approval state must survive
refresh; a normal next message and a decision request have different contracts.
The SDK fake gateway bypasses parts of approval execution, so use fake transport
through the actual approval loop when testing side effects and resumption.

Polymorphic ownership belongs to `HasConversations` and participant-aware lookup.
The relation does not automatically delete conversations when a user disappears.
Account deletion and conversation-message cleanup need explicit verification.

The previous durable-run implementation's atomic transitions, replay cursors,
partial-content persistence and query-count results are historical evidence only.
Direct HTTP streaming has no durable cancellation acknowledgement or recovery
worker. Browser abort does not establish that the provider stopped or that partial
history was saved. Document these boundaries and test safe failure behavior.

SDK deduplication of stored tool results does not establish exactly-once email
execution under simultaneous approval requests or process failure. Keep request
serialization and side-effect regression tests at the application boundary.

Generator templates require execution tests too. The disposable CRUD contract
found ownership leaks, invalid pagination defaults, incompatible SQLite fulltext
DDL, imprecise types and a generated test expecting 200 for a 201 response.
Syntax checking templates alone would not have established their behavior.
