<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Auth;

use App\Http\Controllers\Web\Concerns\ThrottlesWebRequests;
use App\Http\Controllers\Web\Concerns\ValidatesWebRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Thinkycz\LaravelCore\Models\BaseUser;
use Thinkycz\LaravelCore\Support\Resolver;
use Thinkycz\LaravelCore\Support\Typer;
use Thinkycz\LaravelCore\Validation\AuthValidity;

class ForgotPasswordController
{
    use ThrottlesWebRequests;
    use ValidatesWebRequests;

    /**
     * Show the forgot password page.
     */
    public function create(): Response
    {
        return Inertia::render('auth/ForgotPassword');
    }

    /**
     * Send or apply the reset flow configured by the core package.
     */
    public function store(Request $request): RedirectResponse
    {
        $authValidity = AuthValidity::inject();

        $validated = $this->validateRequest($request, [
            'email' => $authValidity->email()->required()->toArray(),
        ]);

        $this->hit($this->limit());

        $user = Typer::assertNullableInstance(Resolver::resolveEloquentUserProvider('users')->retrieveByCredentials([
            'email' => $validated->assertString('email'),
        ]), BaseUser::class);

        if ($user instanceof BaseUser) {
            $broker = Resolver::resolvePasswordBroker('users');
            if (!$broker->getRepository()->recentlyCreatedToken($user)) {
                $user->sendPasswordResetNotification($broker->createToken($user));
            }
        }

        Inertia::flash('success', \__('recovery.sent'));

        return Resolver::resolveRedirector()->to('/forgot-password');
    }
}
