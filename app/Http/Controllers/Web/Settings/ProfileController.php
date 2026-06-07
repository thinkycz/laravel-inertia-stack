<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Settings;

use App\Http\Controllers\Web\Concerns\ValidatesWebRequests;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Thinkycz\LaravelCore\Validation\AuthValidity;

class ProfileController
{
    use ValidatesWebRequests;

    /**
     * Show profile settings.
     */
    public function edit(): Response
    {
        return Inertia::render('settings/Profile');
    }

    /**
     * Update profile settings.
     */
    public function update(Request $request): Response
    {
        $user = User::mustAuth();
        $authValidity = AuthValidity::inject();

        $validated = $this->validateRequest($request, [
            'email' => $authValidity->email()->unique('users', 'email', $user->getKey())->required()->toArray(),
            'locale' => $authValidity->locale()->required()->toArray(),
        ]);

        $user->update([
            'email' => $validated->assertString('email'),
            'locale' => $validated->assertString('locale'),
        ]);

        $request->session()->flash('success', \__('Profile updated.'));

        return Inertia::render('settings/Profile');
    }
}
