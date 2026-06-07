<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Auth;

use App\Http\Controllers\Web\Concerns\ThrottlesWebRequests;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class VerifyEmailController
{
    use ThrottlesWebRequests;

    /**
     * Show the email verification notice.
     */
    public function create(): Response
    {
        return Inertia::render('auth/VerifyEmail');
    }

    /**
     * Resend an email verification message.
     */
    public function store(Request $request): Response
    {
        $this->hit($this->limit());

        $user = User::mustAuth();

        if (!$user->hasVerifiedEmail()) {
            $user->sendEmailVerificationNotification();
        }

        $request->session()->flash('success', \__('Verification email sent.'));

        return Inertia::render('auth/VerifyEmail');
    }
}
