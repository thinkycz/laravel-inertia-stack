<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Auth;

use App\Http\Controllers\Web\Concerns\ThrottlesWebRequests;
use App\Http\Controllers\Web\Concerns\ValidatesWebRequests;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Thinkycz\LaravelCore\Support\Resolver;
use Thinkycz\LaravelCore\Validation\AuthValidity;

class RegisterController
{
    use ThrottlesWebRequests;
    use ValidatesWebRequests;

    /**
     * Show the registration page.
     */
    public function create(): RedirectResponse|Response
    {
        if (User::auth() instanceof User) {
            return Resolver::resolveRedirector()->to('/dashboard');
        }

        return Inertia::render('auth/Register');
    }

    /**
     * Register and authenticate the user.
     */
    public function store(Request $request): SymfonyResponse
    {
        $authValidity = AuthValidity::inject();

        $validated = $this->validateRequest($request, [
            'email' => $authValidity->email()->unique('users', 'email')->required()->toArray(),
            'password' => $authValidity->password()->required()->confirmed()->toArray(),
            'locale' => $authValidity->locale()->required()->toArray(),
        ]);

        $clearThrottle = $this->hit($this->limit());

        DB::transaction(static function () use ($validated): User {
            $user = User::create([
                'email' => $validated->assertString('email'),
                'locale' => $validated->assertString('locale'),
                'password' => $validated->assertString('password'),
            ]);
            Resolver::resolveDatabaseTokenGuard('users')->login($user);
            $user->sendEmailVerificationNotification();

            return $user;
        });

        $request->session()->regenerate();

        $clearThrottle();

        return Resolver::resolveRedirector()->to('/dashboard');
    }
}
