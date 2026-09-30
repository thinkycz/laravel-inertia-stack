<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Settings;

use App\Http\Controllers\Web\Concerns\ThrottlesWebRequests;
use App\Http\Controllers\Web\Concerns\ValidatesWebRequests;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Thinkycz\LaravelCore\Support\Resolver;
use Thinkycz\LaravelCore\Support\Thrower;
use Thinkycz\LaravelCore\Support\Typer;
use Thinkycz\LaravelCore\Validation\AuthValidity;

class SettingsController
{
    use ThrottlesWebRequests;
    use ValidatesWebRequests;

    /**
     * Show the unified settings page.
     */
    public function edit(): Response
    {
        return Inertia::render('settings/Index', ['sessions' => $this->sessions()]);
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

        $user->updateProfile($validated->assertString('email'), $validated->assertString('locale'));

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

        $this->hit($this->limit());

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

        Resolver::resolveDatabaseTokenGuard('users')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        Inertia::flash('success', \__('security.password_updated'));

        return Resolver::resolveRedirector()->to('/login');
    }

    /**
     * List only this user's active login sessions.
     */
    public function index(): Response
    {
        return $this->edit();
    }

    /**
     * Revoke another owned session after confirming the current password.
     */
    public function destroySession(Request $request, int $token): RedirectResponse
    {
        $this->confirmPassword($request);
        $currentId = Resolver::resolveDatabaseTokenGuard('users')->databaseToken?->getKey();
        \abort_if($token === $currentId, 422);
        $session = User::mustAuth()->databaseTokens()->whereKey($token)->firstOrFail();
        $session->delete();
        Inertia::flash('success', \__('security.session_revoked'));

        return Resolver::resolveRedirector()->to('/settings#security');
    }

    /**
     * Delete the authenticated account and its owned records.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $this->confirmPassword($request);
        User::mustAuth()->deleteAccount();
        Resolver::resolveDatabaseTokenGuard('users')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        Inertia::flash('success', \__('security.account_deleted'));

        return Resolver::resolveRedirector()->to('/login');
    }

    /**
     * Serialize session metadata without bearer secrets.
     *
     * @return list<array{id: int, browser: string|null, created_at: string|null, last_used_at: string|null, expires_at: string|null, current: bool}>
     */
    private function sessions(): array
    {
        $sessions = [];
        $currentId = Resolver::resolveDatabaseTokenGuard('users')->databaseToken?->getKey();
        foreach (User::mustAuth()->databaseTokens()->where('expires_at', '>', Carbon::now())->latest()->get() as $token) {
            $sessions[] = [
                'id' => $token->getKey(),
                'browser' => $token->getUserAgent(),
                'created_at' => $token->getCreatedAt()->toJSON(),
                'last_used_at' => $token->getLastUsedAt()?->toJSON(),
                'expires_at' => $token->getExpiresAt()?->toJSON(),
                'current' => $currentId === $token->getKey(),
            ];
        }

        return $sessions;
    }

    /**
     * Confirm sensitive account actions after input validation.
     */
    private function confirmPassword(Request $request): void
    {
        $validated = $this->validateRequest($request, [
            'password' => AuthValidity::inject()->password()->required()->toArray(),
        ]);
        $this->hit($this->limit());
        if (!Resolver::resolveHasher()->check($validated->assertString('password'), User::mustAuth()->getAuthPassword())) {
            Thrower::default()->message('password', Typer::assertString(\__('auth.password')))->throw();
        }
    }
}
