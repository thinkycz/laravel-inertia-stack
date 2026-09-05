<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Password;

use App\Enums\GuardEnum;
use Illuminate\Contracts\Auth\PasswordBroker;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Thinkycz\LaravelCore\Http\ApiFormRequest;
use Thinkycz\LaravelCore\Models\BaseUser;
use Thinkycz\LaravelCore\Routing\AutomaticController;
use Thinkycz\LaravelCore\Support\Config;
use Thinkycz\LaravelCore\Support\Parser;
use Thinkycz\LaravelCore\Support\Resolver;
use Thinkycz\LaravelCore\Support\Typer;
use Thinkycz\LaravelCore\Validation\AuthValidity;

class PasswordResetController extends AutomaticController
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(ApiFormRequest $request): SymfonyResponse
    {
        $validated = $this->validate($request);

        $this->hit($this->limit());

        $passwordDriver = $validated->parseNullableString('guard') ?? $this->getDefaultPasswordDriver();

        $passwordBroker = Resolver::resolvePasswordBroker($passwordDriver);

        $userProvider = Resolver::resolveEloquentUserProvider($this->getUserProviderForPasswordDriver($passwordDriver));

        $user = Typer::assertNullableInstance($userProvider->retrieveByCredentials([
            'email' => $validated->assertString('email'),
        ]), BaseUser::class);

        if ($user instanceof BaseUser === false) {
            $request->thrower()
                ->error('email', PasswordBroker::INVALID_USER)
                ->throw();
        }

        DB::transaction(function () use ($request, $user, $validated, $passwordBroker, $userProvider): void {
            $user = Typer::assertInstance($user->newQuery()->whereKey($user->getKey())->lockForUpdate()->firstOrFail(), BaseUser::class);
            $tokenExists = $user->getEmailForPasswordReset() === $validated->assertString('email') && $passwordBroker->tokenExists($user, $validated->assertString('token'));

            if ($tokenExists === false) {
                $request->thrower()
                    ->error('token', PasswordBroker::INVALID_TOKEN)
                    ->throw();
            }

            $user->update([
                'password' => $validated->assertString('password'),
            ]);

            if ($user->getRememberToken() !== '') {
                $userProvider->updateRememberToken($user, Str::random(60));
            }

            $user->databaseTokens()->getQuery()->delete();

            $passwordBroker->deleteToken($user);
        });

        Resolver::resolveDatabaseTokenGuard($user->getTable())->login($user);

        return $user->meResource()->response();
    }

    /**
     * Validate the incoming request.
     */
    protected function validate(ApiFormRequest $request): Parser
    {
        $authValidity = AuthValidity::inject();

        return $request->builder()
            ->rules([
                'token' => $authValidity->passwordResetToken()->required(),
                'email' => $authValidity->email()->required(),
                'password' => $authValidity->password()->required(),
            ])
            ->guard(GuardEnum::values())
            ->jsonApi()
            ->validate();
    }

    /**
     * Get the default password broker name.
     */
    protected function getDefaultPasswordDriver(): string
    {
        return Config::inject()->assertString('auth.defaults.passwords');
    }

    /**
     * Get the user provider for the given password driver.
     */
    protected function getUserProviderForPasswordDriver(string $passwordDriver): string
    {
        $config = Config::inject();

        $defaultUserProvider = $config->assertString('auth.defaults.provider');

        return $config->assertNullableString('auth.passwords.' . $passwordDriver . '.provider') ?? $defaultUserProvider;
    }
}
