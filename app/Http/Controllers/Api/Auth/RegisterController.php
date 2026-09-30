<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Auth;

use App\Enums\GuardEnum;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Thinkycz\LaravelCore\Exceptions\GenericHttpException;
use Thinkycz\LaravelCore\Http\ApiFormRequest;
use Thinkycz\LaravelCore\Routing\AutomaticController;
use Thinkycz\LaravelCore\Support\Config;
use Thinkycz\LaravelCore\Support\Parser;
use Thinkycz\LaravelCore\Support\Resolver;
use Thinkycz\LaravelCore\Validation\AuthValidity;

class RegisterController extends AutomaticController
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(ApiFormRequest $request): SymfonyResponse
    {
        $validated = $this->validate($request);

        $this->hit($this->limit());

        $guard = $validated->parseNullableString('guard') ?? $this->getDefaultGuard();

        if ($guard === GuardEnum::USERS->value) {
            $user = DB::transaction(static function () use ($validated): User {
                $user = User::create([
                    'email' => $validated->assertString('email'),
                    'locale' => $validated->assertString('locale'),
                    'password' => $validated->assertString('password'),
                ]);
                Resolver::resolveDatabaseTokenGuard($user->getTable())->login($user);
                $user->sendEmailVerificationNotification();

                return $user;
            });
        } else {
            throw GenericHttpException::unauthorized();
        }

        $user->refresh();

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
                'email' => $authValidity->email()->unique('users', 'email')->required(),
                'password' => $authValidity->password()->required(),
                'locale' => $authValidity->locale()->required(),
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
