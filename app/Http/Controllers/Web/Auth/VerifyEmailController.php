<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Auth;

use App\Http\Controllers\Web\Concerns\ThrottlesWebRequests;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Thinkycz\LaravelCore\Support\ThrottleSupport;

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
        $user = User::mustAuth();

        if (!$user->hasVerifiedEmail()) {
            ThrottleSupport::delivery('verification', $user->getEmail());
            $user->sendEmailVerificationNotification();
        }

        Inertia::flash('success', \__('Verification email sent.'));

        return Inertia::render('auth/VerifyEmail');
    }
}
