<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\EmailVerification;

use App\Enums\GuardEnum;
use Illuminate\Auth\Events\Verified;
use Illuminate\Auth\Passwords\PasswordBroker;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Thinkycz\LaravelCore\Exceptions\GenericHttpException;
use Thinkycz\LaravelCore\Http\ApiFormRequest;
use Thinkycz\LaravelCore\Models\BaseUser;
use Thinkycz\LaravelCore\Routing\AutomaticController;
use Thinkycz\LaravelCore\Services\EmailBrokerService;
use Thinkycz\LaravelCore\Support\Config;
use Thinkycz\LaravelCore\Support\Parser;
use Thinkycz\LaravelCore\Support\Resolver;
use Thinkycz\LaravelCore\Support\Typer;
use Thinkycz\LaravelCore\Validation\AuthValidity;

class EmailVerificationVerifyController extends AutomaticController
{
    /**
     * Mark the user's email address as verified.
     */
    public function __invoke(ApiFormRequest $request): SymfonyResponse
    {
        $validated = $this->validate($request);

        $guard = $validated->parseNullableString('guard') ?? $this->getDefaultGuard();

        $userProvider = Resolver::resolveEloquentUserProvider($guard);

        $user = Typer::assertNullableInstance($userProvider->retrieveByCredentials([
            'email' => $validated->assertString('email'),
        ]), BaseUser::class);

        if ($user instanceof BaseUser === false) {
            $request->thrower()
                ->error('email', PasswordBroker::INVALID_USER)
                ->throw();
        }

        if ($user instanceof MustVerifyEmail === false) {
            throw GenericHttpException::forbidden();
        }

        if ($user->hasVerifiedEmail()) {
            return Resolver::resolveResponseFactory()->noContent();
        }

        DB::transaction(function () use ($request, $user, $validated): void {
            $current = $user->newQuery()->whereKey($user->getKey())->where('email', $validated->assertString('email'))->lockForUpdate()->first();
            if (!$current instanceof BaseUser || !$current instanceof MustVerifyEmail || !EmailBrokerService::inject()->validate($current->getTable(), $current->getEmailForVerification(), $validated->assertString('token'))) {
                $request->thrower()->error('token', PasswordBroker::INVALID_TOKEN)->throw();
            }
            $current->markEmailAsVerified();
            EmailBrokerService::inject()->confirm($current->getTable(), $current->getEmailForVerification());
        });
        $user->refresh();

        Resolver::resolveEventDispatcher()->dispatch(new Verified($user));

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
                'token' => $authValidity->emailVerificationToken()->required(),
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
