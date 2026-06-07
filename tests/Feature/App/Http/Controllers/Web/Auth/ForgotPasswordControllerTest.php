<?php

declare(strict_types=1);

namespace Tests\Feature\App\Http\Controllers\Web\Auth;

use App\Models\User;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;
use Thinkycz\LaravelCore\Models\DatabaseToken;
use Thinkycz\LaravelCore\Notifications\PasswordNewPasswordSettedNotification;
use Thinkycz\LaravelCore\Support\Typer;

class ForgotPasswordControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_view_forgot_password_page(): void
    {
        $response = $this->get('/forgot-password', $this->inertiaHeaders());

        $response->assertOk();
        $response->assertJsonPath('component', 'auth/ForgotPassword');
    }

    public function test_unknown_email_returns_validation_error(): void
    {
        $response = $this->post('/forgot-password', [
            'email' => 'nobody@example.com',
        ]);

        $response->assertStatus(422);
    }

    public function test_known_email_updates_password_and_sends_notification(): void
    {
        Notification::fake();

        $user = Typer::assertInstance(UserFactory::new()->createOne(), User::class);
        $originalHash = $user->getAuthPassword();

        $response = $this->post('/forgot-password', [
            'email' => $user->getEmail(),
        ], $this->inertiaHeaders());

        $response->assertOk();
        $response->assertJsonPath('component', 'auth/ForgotPassword');
        $response->assertSessionHas('success');

        $user->refresh();

        static::assertNotSame($originalHash, $user->getAuthPassword());

        Notification::assertSentTo($user, PasswordNewPasswordSettedNotification::class);
    }

    public function test_known_email_revokes_existing_database_tokens(): void
    {
        $user = Typer::assertInstance(UserFactory::new()->createOne(), User::class);

        DatabaseToken::inject()
            ->login($user->getTable(), $user);

        $this->assertDatabaseCount('database_tokens', 1);

        $this->post('/forgot-password', [
            'email' => $user->getEmail(),
        ]);

        $this->assertDatabaseCount('database_tokens', 0);
    }
}
