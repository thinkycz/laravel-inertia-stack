<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Auth;

use App\Http\Controllers\Web\Concerns\ThrottlesWebRequests;
use App\Http\Controllers\Web\Concerns\ValidatesWebRequests;
use Illuminate\Contracts\Auth\PasswordBroker;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Thinkycz\LaravelCore\Models\BaseUser;
use Thinkycz\LaravelCore\Support\Resolver;
use Thinkycz\LaravelCore\Support\ThrottleSupport;
use Thinkycz\LaravelCore\Support\Thrower;
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
     * Send a reset link without changing credentials.
     */
    public function store(Request $request): Response
    {
        $authValidity = AuthValidity::inject();

        $validated = $this->validateRequest($request, [
            'email' => $authValidity->email()->required()->toArray(),
        ]);

        $user = Typer::assertNullableInstance(Resolver::resolveEloquentUserProvider('users')->retrieveByCredentials([
            'email' => $validated->assertString('email'),
        ]), BaseUser::class);

        if ($user instanceof BaseUser === false) {
            Thrower::default()->message('email', Typer::assertString(\__(PasswordBroker::INVALID_USER)))->throw();
        }

        $broker = Resolver::resolvePasswordBroker('users');
        ThrottleSupport::delivery('password', $user->getEmailForPasswordReset());
        $user->sendPasswordResetNotification($broker->createToken($user));

        Inertia::flash('success', \__('A password reset link has been sent to your email address.'));

        return Inertia::render('auth/ForgotPassword');
    }
}
