<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Password;

use App\Enums\GuardEnum;
use Illuminate\Contracts\Auth\PasswordBroker;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Thinkycz\LaravelCore\Http\ApiFormRequest;
use Thinkycz\LaravelCore\Models\BaseUser;
use Thinkycz\LaravelCore\Routing\AutomaticController;
use Thinkycz\LaravelCore\Support\Config;
use Thinkycz\LaravelCore\Support\Parser;
use Thinkycz\LaravelCore\Support\Resolver;
use Thinkycz\LaravelCore\Support\ThrottleSupport;
use Thinkycz\LaravelCore\Support\Typer;
use Thinkycz\LaravelCore\Validation\AuthValidity;

class PasswordForgotController extends AutomaticController
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(ApiFormRequest $request): SymfonyResponse
    {
        $validated = $this->validate($request);

        $guard = $validated->parseNullableString('guard') ?? $this->getDefaultPasswordDriver();

        $userProvider = Resolver::resolveEloquentUserProvider($guard);

        $user = Typer::assertNullableInstance($userProvider->retrieveByCredentials([
            'email' => $validated->assertString('email'),
        ]), BaseUser::class);

        if ($user instanceof BaseUser === false) {
            $request->thrower()
                ->error('email', PasswordBroker::INVALID_USER)
                ->throw();
        }

        $passwordBroker = Resolver::resolvePasswordBroker($guard);
        ThrottleSupport::delivery('password', $user->getEmailForPasswordReset());
        $user->sendPasswordResetNotification($passwordBroker->createToken($user));

        return Resolver::resolveResponseFactory()->noContent();
    }

    /**
     * Validate the incoming request.
     */
    protected function validate(ApiFormRequest $request): Parser
    {
        $authValidity = AuthValidity::inject();

        return $request->builder()
            ->rules([
                'email' => $authValidity->email()->required(),
            ])
            ->guard(GuardEnum::values())
            ->validate();
    }

    /**
     * Get the default password broker name.
     */
    protected function getDefaultPasswordDriver(): string
    {
        return Config::inject()->assertString('auth.defaults.passwords');
    }
}
