<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Auth;

use App\Http\Controllers\Web\Concerns\ThrottlesWebRequests;
use App\Http\Controllers\Web\Concerns\ValidatesWebRequests;
use Illuminate\Contracts\Auth\PasswordBroker;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
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
    public function store(Request $request): Response
    {
        $this->hit($this->limit());

        $authValidity = AuthValidity::inject();

        $validated = $this->validateRequest($request, [
            'email' => $authValidity->email()->required()->toArray(),
        ]);

        $user = Typer::assertNullableInstance(Resolver::resolveEloquentUserProvider('users')->retrieveByCredentials([
            'email' => $validated->assertString('email'),
        ]), BaseUser::class);

        if ($user instanceof BaseUser === false) {
            throw ValidationException::withMessages([
                'email' => \__(PasswordBroker::INVALID_USER),
            ]);
        }

        $password = Str::password(16);

        $user->update([
            'password' => $password,
        ]);

        $user->databaseTokens()->getQuery()->delete();

        $user->sendPasswordNewPasswordSettedNotification($password);

        $request->session()->flash('success', \__('A new password has been sent to your email address.'));

        return Inertia::render('auth/ForgotPassword');
    }
}
