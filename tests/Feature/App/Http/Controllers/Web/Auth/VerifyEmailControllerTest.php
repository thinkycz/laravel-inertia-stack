<?php

declare(strict_types=1);

namespace Tests\Feature\App\Http\Controllers\Web\Auth;

use App\Models\User;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;
use Thinkycz\LaravelCore\Notifications\EmailVerificationNotification;
use Thinkycz\LaravelCore\Support\Typer;

class VerifyEmailControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_view_verify_email_page(): void
    {
        $user = Typer::assertInstance(UserFactory::new()->createOne(), User::class);

        $response = $this->be($user, 'users')->get('/verify-email', $this->inertiaHeaders());

        $response->assertOk();
        $response->assertJsonPath('component', 'auth/VerifyEmail');
    }

    public function test_authenticated_unverified_user_triggers_verification_notification(): void
    {
        Notification::fake();

        $user = Typer::assertInstance(UserFactory::new()->unverified()->createOne(), User::class);

        static::assertNull($user->getEmailVerifiedAt());

        $response = $this->be($user, 'users')->post('/verify-email', [], $this->inertiaHeaders());

        $response->assertOk();
        $response->assertJsonPath('component', 'auth/VerifyEmail');
        $response->assertSessionHas('success');

        Notification::assertSentTo($user, EmailVerificationNotification::class);
    }

    public function test_already_verified_user_does_not_receive_another_notification(): void
    {
        Notification::fake();

        $user = Typer::assertInstance(UserFactory::new()->createOne(), User::class);
        $user->markEmailAsVerified();

        $response = $this->be($user, 'users')->post('/verify-email', [], $this->inertiaHeaders());

        $response->assertOk();
        $response->assertJsonPath('component', 'auth/VerifyEmail');

        Notification::assertNothingSent();
    }

    public function test_guest_cannot_resend_verification(): void
    {
        Notification::fake();

        $response = $this->post('/verify-email');

        $response->assertRedirect('/login');
    }
}
