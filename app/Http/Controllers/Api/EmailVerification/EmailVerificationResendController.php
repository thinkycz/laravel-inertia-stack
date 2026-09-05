<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\EmailVerification;

use App\Enums\GuardEnum;
use Illuminate\Auth\Passwords\PasswordBroker;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Thinkycz\LaravelCore\Exceptions\GenericHttpException;
use Thinkycz\LaravelCore\Http\ApiFormRequest;
use Thinkycz\LaravelCore\Models\BaseUser;
use Thinkycz\LaravelCore\Routing\AutomaticController;
use Thinkycz\LaravelCore\Support\Config;
use Thinkycz\LaravelCore\Support\Parser;
use Thinkycz\LaravelCore\Support\Resolver;
use Thinkycz\LaravelCore\Support\ThrottleSupport;
use Thinkycz\LaravelCore\Support\Typer;
use Thinkycz\LaravelCore\Validation\AuthValidity;

class EmailVerificationResendController extends AutomaticController
{
    /**
     * Resend the email verification notification.
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

        ThrottleSupport::delivery('verification', $user->getEmailForPasswordReset());
        $user->sendEmailVerificationNotification();

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
     * Get the default guard name.
     */
    protected function getDefaultGuard(): string
    {
        return Config::inject()->assertString('auth.defaults.guard');
    }
}
