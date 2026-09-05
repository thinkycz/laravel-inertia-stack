<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Me;

use App\Enums\GuardEnum;
use Illuminate\Auth\AuthenticationException;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Thinkycz\LaravelCore\Http\ApiFormRequest;
use Thinkycz\LaravelCore\Models\BaseUser;
use Thinkycz\LaravelCore\Routing\AutomaticController;
use Thinkycz\LaravelCore\Support\Config;
use Thinkycz\LaravelCore\Support\Parser;
use Thinkycz\LaravelCore\Support\Resolver;
use Thinkycz\LaravelCore\Validation\AuthValidity;
use Thinkycz\LaravelCore\Validation\Validity;

class MeUpdateController extends AutomaticController
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

        $user->fill([
            'email' => $validated->parseNullableString('email') ?? $user->getEmail(),
            'locale' => $validated->parseNullableString('locale') ?? $user->getLocale(),
        ]);

        if ($user->isDirty('email')) {
            $user->forceFill(['email_verified_at' => null]);
        }

        $user->save();

        return $user->meResource()->response();
    }

    /**
     * Validate the incoming request.
     *
     * Two passes are needed because the second pass's `unique` rule for
     * `email` depends on the user identified by the `guard` field. The
     * first pass extracts the guard and registers the same keys with
     * `unsafe()` rules so the core's `SecureValidator` does not mark
     * them as missing; the second pass replaces those rules with the
     * real type/format validation.
     */
    protected function validate(ApiFormRequest $request): Parser
    {
        $authValidity = AuthValidity::inject();

        $guard = $request->builder()
            ->rules([
                'email' => Validity::make()->unsafe(),
                'locale' => Validity::make()->unsafe(),
            ])
            ->guard(GuardEnum::values())
            ->jsonApi()
            ->validate()
            ->parseNullableString('guard') ?? GuardEnum::USERS->value;

        $user = Resolver::resolveAuthManager()->guard($guard)->user();

        if ($user instanceof BaseUser === false) {
            throw new AuthenticationException();
        }

        return $request->builder()
            ->rules([
                'email' => $authValidity->email()->unique($guard, 'email', $user->getKey())->nullable()->filled(),
                'locale' => $authValidity->locale()->nullable()->filled(),
            ])
            ->guard(GuardEnum::values())
            ->jsonApi()
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
