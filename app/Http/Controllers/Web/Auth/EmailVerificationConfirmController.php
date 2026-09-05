<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Auth;

use App\Enums\GuardEnum;
use App\Http\Controllers\Web\Concerns\ThrottlesWebRequests;
use App\Http\Controllers\Web\Concerns\ValidatesWebRequests;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Thinkycz\LaravelCore\Services\EmailBrokerService;
use Thinkycz\LaravelCore\Support\Resolver;
use Thinkycz\LaravelCore\Support\Typer;
use Thinkycz\LaravelCore\Validation\AuthValidity;

class EmailVerificationConfirmController
{
    use ThrottlesWebRequests;
    use ValidatesWebRequests;

    /**
     * Consume the verification token from the email link and mark the
     * user's email as verified.
     *
     * The core EmailVerificationNotification builds a URL of the form
     * `<spa.email_verification_url>?guard=<g>&email=<e>&token=<t>&locale=<l>`.
     * This controller is the SPA target of that URL. It does not require
     * the visitor to be authenticated: the token is the secret, the
     * email is the key, and the guard scopes the lookup. The visitor
     * is then redirected to the dashboard (if they are already signed
     * in as the verified user) or to the login page (so they can sign
     * in with the now-verified address).
     */
    public function __invoke(Request $request): RedirectResponse
    {
        $authValidity = AuthValidity::inject();

        $validated = $this->validateRequest($request, [
            'guard' => $authValidity->baseValidity->mode(GuardEnum::values())->required()->toArray(),
            'email' => $authValidity->email()->required()->toArray(),
            'token' => $authValidity->emailVerificationToken()->required()->toArray(),
        ]);

        $clearThrottle = $this->hit($this->limit());

        $guard = $validated->assertString('guard');
        $email = $validated->assertString('email');
        $token = $validated->assertString('token');

        $userProvider = Resolver::resolveEloquentUserProvider($guard);

        $user = Typer::assertNullableInstance($userProvider->retrieveByCredentials([
            'email' => $email,
        ]), User::class);

        if ($user instanceof User === false) {
            return $this->redirectWithError('/login', \__('We can\'t find a user with that email address.'));
        }

        if ($user->hasVerifiedEmail()) {
            $clearThrottle();

            return $this->redirectAfterConfirm($user, \__('Email already verified.'));
        }

        $confirmed = DB::transaction(static function () use ($user, $guard, $email, $token): bool {
            $current = User::query()->whereKey($user->getKey())->where('email', $email)->lockForUpdate()->first();
            if ($current === null || !EmailBrokerService::inject()->validate($guard, $email, $token)) {
                return false;
            }
            $current->markEmailAsVerified();
            EmailBrokerService::inject()->confirm($guard, $email);

            return true;
        });
        if (!$confirmed) {
            return $this->redirectWithError('/login', \__('The verification link is invalid or has expired.'));
        }
        $user->refresh();

        Resolver::resolveEventDispatcher()->dispatch(new Verified($user));

        $clearThrottle();

        return $this->redirectAfterConfirm($user, \__('Email verified.'));
    }

    /**
     * Redirect after a successful confirmation.
     *
     * If the visitor is already authenticated as the user that was just
     * verified, drop them on the dashboard. Otherwise send them to the
     * login page so they can sign in with the now-verified address.
     */
    protected function redirectAfterConfirm(User $user, mixed $message): RedirectResponse
    {
        $authUser = User::auth();

        if ($authUser instanceof User && $authUser->getKey() === $user->getKey()) {
            Inertia::flash('success', $message);

            return Resolver::resolveRedirector()->to('/dashboard');
        }

        Inertia::flash('success', $message);

        return Resolver::resolveRedirector()->to('/login');
    }

    /**
     * Redirect with an Inertia-flashed error message.
     *
     * `Inertia::flash()` is preferred over `redirect()->with()` here
     * because the Inertia middleware reflashes the data on every
     * request, so the message survives the 302 → guest-redirect →
     * final Inertia render chain. A plain `with()` would expire
     * after the intermediate redirect, which is the case for the
     * authenticated visitor who gets bounced from /login to /dashboard.
     */
    protected function redirectWithError(string $path, mixed $message): RedirectResponse
    {
        Inertia::flash('error', $message);

        return Resolver::resolveRedirector()->to($path);
    }
}
