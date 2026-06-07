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
`session()->flash()` + same-request `Inertia::render()`.

### Validation errors need a re-render with the **originating** component

**Problem.** The Inertia-aware `ValidationException` handler
initially rendered `auth/Login` for every form path. A 422 from
`/forgot-password` re-rendered the login page; the field errors
arrived but the form was gone.

**Why it surprised us.** Inertia v3 form submits do **not** set the
`X-Inertia-Partial-Component` header (only partial reloads do), so
we couldn't read the originating component from the request.

**Fix.** Resolve the component from the request path with a
match expression in `bootstrap/app.php`. The `X-Inertia-Partial-
Component` header still takes precedence for partial reloads.

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

### `@phpstan-ignore` is forbidden, use `mixed` parameter types

**Problem.** `__()` returns `array|string`. Controllers passing
the result to a method typed `string $message` fail PHPStan with
`argument.type`. `(string)` cast is rejected ("Cannot cast
(array|string) to string"). The temptation is to add
`@phpstan-ignore-next-line` or widen the parameter to `array`.

**Why it's tempting.** `@phpstan-ignore` is the universal escape
hatch. The project makes it a hard rule that suppressors throw.

**Fix.** The receiver takes `mixed $message`. The caller passes
`__('...')` directly; the body stores or compares loosely. The
project's `no_phpstan_ignore` rule (see `phpstan.neon`) enforces
this and will surface violations.

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

### E2e flash chains are fragile; phpunit is the better signal

**Problem.** We spent significant time trying to make the browser
follow the 302 → guest-redirect → final render chain and surface a
flashed error. Browser session-cookie lifecycle differs from
phpunit's request client; the share callback reads from a session
that does not match what phpunit sees in the same test.

**Why it was tempting.** "If it works in the browser, it's real."

**Fix.** Use phpunit for cross-request flash coverage. E2e covers
single-request flashes (like the resend button on
`/verify-email`). The e2e for the invalid-token flash chain was
dropped in commit `3cafd3a`; phpunit covers the same logic with a
real token.

## Dev environment

### `php artisan serve` workers persist; `pkill -f "artisan serve"` is not enough

**Problem.** After killing `php artisan serve`, `lsof -i :8000` still
shows a listener. Subsequent tests time out or land on the wrong
port (8001, 8002, 8003 if 8000 was taken).

**Why it surprised us.** The process is `php -S 127.0.0.1:8000 ...`
started by `artisan serve`. `pkill` against the artisan command
matches the parent shell but not the child PHP `-S` workers.

**Signal.** The dev server returns 200 to `/up` but requests to
other routes hang. `lsof -i :8000` shows multiple PIDs.

**Fix.**

```sh
pkill -9 -f "artisan serve"
pkill -9 -f "server.php"     # the underlying -S workers
lsof -i :8000               # verify clean
```

For Playwright, the `webServer` block in `playwright.config.ts`
auto-restarts on a new port if 8000 is taken, which masks the
problem but produces confusing logs.

### Playwright's `__Host-` Secure cookie is sent over `127.0.0.1` HTTP

**Problem.** `packages/thinkycz/laravel-core/src/Guards/DatabaseTokenGuard.php::cookieSecure()`
returns `!appEnvIs(['local'])`, so in `testing` the database token
cookie is `__Host-...; Secure`. RFC 6265bis says `__Host-` cookies
should only be set over HTTPS. Yet the e2e tests authenticate
correctly over `http://127.0.0.1:8000`.

**Why it works anyway.** Chromium treats `127.0.0.1` (and
`localhost`) as a "potentially trustworthy origin" per the
Secure Contexts spec, so the browser sends the cookie.

**Implication.** Don't waste time on `Secure` flag debugging for
`__Host-` cookies over localhost. They are sent.

### E2e `Log::info()` is a no-op in the testing env

**Problem.** The e2e dev server runs with `APP_ENV=testing`. The
default log channel in `testing` is `'null'`
(`config/logging.php:23`). So `Log::info('debug')` writes nothing.

**Why it surprised us.** Logs from phpunit tests (which run in
`testing` too) sometimes show up. But the dev server's log channel
is a different code path that resolves the same config.

**Fix.** For dev-server debugging, use
`file_put_contents('/tmp/trace.log', ..., FILE_APPEND)` from inside
the controller or middleware. Read the file with `cat` or
`tail -f`. Clean up the debug code and `/tmp/trace.log` before
committing.

## Workflow

### `make` is the only entry; never run ad-hoc scripts

**Why.** The Makefile encodes the exact pipeline: `pint` →
`prettier` → `phpstan` → audits → `phpunit` → `playwright`. Ad-hoc
shell commands (`./vendor/bin/pint`, `npx playwright test`)
miss audits or run against stale caches.

**Signal.** If you find yourself running commands in series, ask
whether a `make` target covers it. The `Makefile` is the source of
truth.

### Run `make fix` then `make check` before every commit

**Why.** Pint reorders class elements, alphabetizes imports, and
rewrites native function calls. PHPStan catches type errors that
are invisible at runtime. Prettier aligns the frontend. Audits
flag supply-chain issues. Without the loop, the next `make check`
on CI fails on a fixable lint issue.

### The phase 4 verification doc is the source of decisions

`docs/verification/phase4-e2e-2026-06-07.md` records the Inertia v3
quirks, e2e gotchas, and the validation-handler fix. Read it before
touching auth or forms.
