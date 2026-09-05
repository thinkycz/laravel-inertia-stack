<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Auth;

use App\Http\Controllers\Web\Concerns\ThrottlesWebRequests;
use App\Http\Controllers\Web\Concerns\ValidatesWebRequests;
use Illuminate\Contracts\Auth\PasswordBroker;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Thinkycz\LaravelCore\Models\BaseUser;
use Thinkycz\LaravelCore\Support\Resolver;
use Thinkycz\LaravelCore\Support\Thrower;
use Thinkycz\LaravelCore\Support\Typer;
use Thinkycz\LaravelCore\Validation\AuthValidity;

class ResetPasswordController
{
    use ThrottlesWebRequests;
    use ValidatesWebRequests;

    /**
     * Show the reset password page.
     */
    public function create(Request $request): Response
    {
        return Inertia::render('auth/ResetPassword', [
            'email' => $request->string('email')->toString(),
            'token' => $request->string('token')->toString(),
        ]);
    }

    /**
     * Reset the user's password.
     */
    public function store(Request $request): SymfonyResponse
    {
        $authValidity = AuthValidity::inject();

        $validated = $this->validateRequest($request, [
            'email' => $authValidity->email()->required()->toArray(),
            'password' => $authValidity->password()->required()->toArray(),
            'token' => $authValidity->passwordResetToken()->required()->toArray(),
        ]);

        $clearThrottle = $this->hit($this->limit());

        $user = Typer::assertNullableInstance(Resolver::resolveEloquentUserProvider('users')->retrieveByCredentials([
            'email' => $validated->assertString('email'),
        ]), BaseUser::class);

        if ($user instanceof BaseUser === false) {
            Thrower::default()->message('email', Typer::assertString(\__(PasswordBroker::INVALID_USER)))->throw();
        }

        $broker = Resolver::resolvePasswordBroker('users');

        DB::transaction(function () use ($user, $validated, $broker): void {
            $user = Typer::assertInstance($user->newQuery()->whereKey($user->getKey())->lockForUpdate()->firstOrFail(), BaseUser::class);

            if ($user->getEmailForPasswordReset() !== $validated->assertString('email') || !$broker->tokenExists($user, $validated->assertString('token'))) {
                Thrower::default()->message('token', Typer::assertString(\__(PasswordBroker::INVALID_TOKEN)))->throw();
            }

            $user->update([
                'password' => $validated->assertString('password'),
            ]);

            if ($user->getRememberToken() !== '') {
                Resolver::resolveEloquentUserProvider('users')->updateRememberToken($user, Str::random(60));
            }

            $user->databaseTokens()->getQuery()->delete();
            $broker->deleteToken($user);
        });

        Resolver::resolveDatabaseTokenGuard('users')->login($user);

        $clearThrottle();

        return Resolver::resolveRedirector()->to('/dashboard');
    }
}
