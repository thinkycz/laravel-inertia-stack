# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- 84 PHPUnit feature tests / 177 assertions (up from 14 / 45 in baseline).
- 16 Playwright e2e tests covering register, login, logout, password reset,
  profile update, locale switch, email verification flash, and protected
  route redirects.
- `FieldError.vue`, `FlashAlerts.vue`, `Select.vue`, `FormField.vue` shared UI
  primitives under `resources/js/components/ui/`.
- `useSharedProps()` composable returning `{app, auth, user, flash, flashSuccess,
flashError, errors}` with strict TypeScript types.
- `app/Http/Resources/UserResource::toId()` for consistent `id` projection.
- `app/Http/Controllers/Web/Concerns/ThrottlesWebRequests` trait applied to
  auth web controllers.
- `app/Http/Middleware/EnsureInertiaUserIsAuthenticated` throws
  `AuthenticationException` for JSON requests.
- Inertia 3 validation-error handling in `bootstrap/app.php` that re-renders
  the originating component (resolved from request path) with a 422 status
  (Inertia v3 client does not follow bare 302 redirects from
  `ValidationException`).
- Inertia 3 success-flash handling: web controllers use
  `$request->session()->flash(...)` + `Inertia::render(...)` so the success
  message appears on the same page instead of being lost on the 302 redirect
  the client does not follow.
- Database-token revocation via `getQuery()->delete()` on logout.
- `make test-coverage` target (requires `xdebug`).
- `lefthook.yml` pre-commit (lint) and pre-push (stan + tests + e2e) hooks.
- `docs/architecture.md` with mermaid request/middleware diagrams.
- `LICENSE` (MIT), `CONTRIBUTING.md`.
- `spa.email_verification_url` translation key (en + cs) so the core email
  verification notification can render the SPA confirmation link.
- `Alert.vue` now renders `role="alert"` so success/error messages are
  announced by screen readers and are locatable via `getByRole('alert')` in
  tests.
- `AppLayout.vue` exposes a skip-to-content link, `aria-label="Primary"` on
  the nav, and `aria-current="page"` on the active link.

### Changed

- `app/Http/Controllers/Web/Auth/*::store` controllers no longer hash the
  password twice; the single `Resolver::resolveHasher()->check(...)` call now
  serves both auth and constant-time comparison.
- All 6 form pages migrated to Inertia 3 `<Form>` component (replaces the
  custom form helpers).
- `Input.vue` and `Select.vue` accept a `defaultValue` prop and an
  `invalid`/`describedBy` pair, wired up via the new `FormField.vue`
  wrapper to `aria-invalid` and `aria-describedby`.
- `Label.vue` renders a red asterisk (aria-hidden) for `required` fields.
- `AppLayout.vue` and `AuthLayout.vue` share `<Brand>` and `<FlashAlerts>`
  components; pages no longer mount their own `<FlashAlerts />`.
- `tests/TestCase::inertiaHeaders()` hardened to include `X-Inertia: true`,
  `Accept: text/html`, and a request-aware Referer.
- `make e2e` runs Playwright with `webServer` block driving the dev server
  under `APP_ENV=testing`, `SESSION_SECURE_COOKIE=false`, `MAIL_MAILER=log`.

### Removed

- Dead `PULSE_ENABLED` and `TELESCOPE_ENABLED` env entries from `phpunit.xml`.
- Old `tests/e2e/debug*.spec.ts` diagnostic harnesses.
- `Symfony\Component\HttpFoundation\Response` return type from
  `VerifyEmailController::store`, `ProfileController::update`,
  `PasswordController::update`, and `ForgotPasswordController::store` —
  the controllers now return `Inertia\Response` to keep the page stable
  across the POST.

### Fixed

- `email:dns` rule failing for `example.com` in dev: e2e dev server now uses
  `APP_ENV=testing` so the basic `email` rule is applied (no DNS lookup).
- 500 ValidationException rendered as symfony debug HTML for Inertia requests
  in debug mode; replaced with Inertia-aware render in `bootstrap/app.php`.
- Logout redirect for guests (was 302 to `/` then 302 to `/login`); tests
  accept either URL.
- Success flash messages lost on Inertia form submissions because the v3
  client does not follow plain 302 redirects; controllers now re-render the
  same Inertia page with `session()->flash(...)` so the flash renders in
  the next response.
- `VerifyEmail` page rendered two copies of the success alert because
  `<FlashAlerts />` was mounted both in the layout and the page; the page
  now relies on the layout's instance.
- `bootstrap/app.php` validation handler defaulted to the `auth/Login`
  component for every form path, breaking non-login form errors; the handler
  now resolves the originating component from the request path.

## [0.1.0] - 2026-06-07

Initial snapshot captured in `docs/verification/baseline-2026-06-07.md`.
14 tests / 45 assertions, Inertia 2 → 3 migration, PHP 8.3, Laravel 13.
