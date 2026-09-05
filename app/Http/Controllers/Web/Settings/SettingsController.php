<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Settings;

use App\Http\Controllers\Web\Concerns\ValidatesWebRequests;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Thinkycz\LaravelCore\Support\Resolver;
use Thinkycz\LaravelCore\Support\Thrower;
use Thinkycz\LaravelCore\Support\Typer;
use Thinkycz\LaravelCore\Validation\AuthValidity;

class SettingsController
{
    use ValidatesWebRequests;

    /**
     * Show the unified settings page.
     */
    public function edit(): Response
    {
        return Inertia::render('settings/Index');
    }

    /**
     * Update profile details (email, locale).
     */
    public function updateProfile(Request $request): RedirectResponse
    {
        $user = User::mustAuth();
        $authValidity = AuthValidity::inject();

        $validated = $this->validateRequest($request, [
            'email' => $authValidity->email()->unique('users', 'email', $user->getKey())->required()->toArray(),
            'locale' => $authValidity->locale()->required()->toArray(),
        ]);

        $user->fill([
            'email' => $validated->assertString('email'),
            'locale' => $validated->assertString('locale'),
        ]);

        if ($user->isDirty('email')) {
            $user->forceFill(['email_verified_at' => null]);
        }

        $user->save();

        Inertia::flash('success', \__('Profile updated.'));

        return Resolver::resolveRedirector()->back();
    }

    /**
     * Update the user's password.
     */
    public function updatePassword(Request $request): RedirectResponse
    {
        $user = User::mustAuth();
        $authValidity = AuthValidity::inject();

        $validated = $this->validateRequest($request, [
            'password' => $authValidity->password()->required()->toArray(),
            'new_password' => $authValidity->password()->required()->toArray(),
        ]);

        $hasher = Resolver::resolveHasher();

        if (!$hasher->check($validated->assertString('password'), $user->getAuthPassword())) {
            Thrower::default()->message('password', Typer::assertString(\__('auth.password')))->throw();
        }

        DB::transaction(function () use ($user, $validated): void {
            $user->update([
                'password' => $validated->assertString('new_password'),
            ]);

            $user->databaseTokens()->getQuery()->delete();
        });

        Inertia::flash('success', \__('Password updated.'));

        return Resolver::resolveRedirector()->back();
    }
}
