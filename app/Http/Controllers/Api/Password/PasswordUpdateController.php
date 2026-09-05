<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Password;

use App\Enums\GuardEnum;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Thinkycz\LaravelCore\Http\ApiFormRequest;
use Thinkycz\LaravelCore\Models\BaseUser;
use Thinkycz\LaravelCore\Routing\AutomaticController;
use Thinkycz\LaravelCore\Support\Config;
use Thinkycz\LaravelCore\Support\Parser;
use Thinkycz\LaravelCore\Support\Resolver;
use Thinkycz\LaravelCore\Validation\AuthValidity;

class PasswordUpdateController extends AutomaticController
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(ApiFormRequest $request): SymfonyResponse
    {
        $validated = $this->validate($request);

        $this->hit($this->limit());

        $guard = $validated->parseNullableString('guard') ?? $this->getDefaultGuard();

        $user = Resolver::resolveAuthManager()->guard($guard)->user();

        if ($user instanceof BaseUser === false) {
            throw new AuthenticationException();
        }

        $hasher = Resolver::resolveHasher();

        $passwordMatches = $hasher->check($validated->assertString('password'), $user->getAuthPassword());

        if ($passwordMatches === false) {
            $request->thrower()
                ->error('password', 'auth.password')
                ->throw();
        }

        DB::transaction(function () use ($user, $validated): void {
            $user->update([
                'password' => $validated->assertString('new_password'),
            ]);

            $user->databaseTokens()->getQuery()->delete();
        });

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
                'password' => $authValidity->password()->required(),
                'new_password' => $authValidity->password()->required(),
            ])
            ->guard(GuardEnum::values())
            ->validate();
    }

    /**
     * Get the default guard name.
     */
    protected function getDefaultGuard(): string
    {
        return Config::inject()->assertString('auth.defaults.guard');
    }
}
