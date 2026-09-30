<?php

declare(strict_types=1);

use App\Models\User;
use Database\Factories\UserFactory;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Notification;
use Thinkycz\LaravelCore\Models\DatabaseToken;
use Thinkycz\LaravelCore\Notifications\PasswordNewPasswordSettedNotification;
use Thinkycz\LaravelCore\Notifications\PasswordResetNotification;
use Thinkycz\LaravelCore\Support\Typer;

\test('guest can view forgot password page', function (): void {
    $response = $this->get('/forgot-password', $this->inertiaHeaders());

    $response->assertOk();
    $response->assertJsonPath('component', 'auth/ForgotPassword');
});

\test('unknown email receives a neutral recovery response', function (): void {
    $response = $this->post('/forgot-password', [
        'email' => 'nobody@example.com',
    ]);

    $response->assertRedirect('/forgot-password');
    $response->assertSessionHasNoErrors();
});

\test('known email keeps the password and sends a reset link', function (): void {
    Notification::fake();

    $user = Typer::assertInstance(UserFactory::new()->createOne(), User::class);
    $originalHash = $user->getAuthPassword();

    $response = $this->post('/forgot-password', [
        'email' => $user->getEmail(),
    ], $this->inertiaHeaders());

    $response->assertRedirect('/forgot-password');
    \assertInertiaFlash($response, 'success', \__('recovery.sent'));

    $user->refresh();

    static::assertSame($originalHash, $user->getAuthPassword());

    Notification::assertSentTo($user, PasswordResetNotification::class);
});

\test('requesting a reset preserves existing database tokens', function (): void {
    $user = Typer::assertInstance(UserFactory::new()->createOne(), User::class);

    DatabaseToken::inject()
        ->login($user->getTable(), $user);

    $this->assertDatabaseCount('database_tokens', 1);

    $this->post('/forgot-password', [
        'email' => $user->getEmail(),
    ]);

    $this->assertDatabaseCount('database_tokens', 1);
});

\test('standard broker flow runs when send_raw_password is disabled', function (): void {
    Config::set('auth.passwords.users.send_raw_password', false);

    Notification::fake();

    $user = Typer::assertInstance(UserFactory::new()->createOne(), User::class);
    $originalHash = $user->getAuthPassword();

    $response = $this->post('/forgot-password', [
        'email' => $user->getEmail(),
    ], $this->inertiaHeaders());

    $response->assertRedirect('/forgot-password');
    \assertInertiaFlash($response, 'success', \__('recovery.sent'));

    $user->refresh();

    // Password is NOT regenerated in the broker flow; only a token is sent.
    static::assertSame($originalHash, $user->getAuthPassword());

    Notification::assertSentTo($user, PasswordResetNotification::class);
    Notification::assertNotSentTo($user, PasswordNewPasswordSettedNotification::class);
});

\test('valid recovery requests retain throttle hits while invalid input does not consume quota', function (): void {
    Notification::fake();
    for ($i = 0; $i < 7; ++$i) {
        $this->post('/forgot-password', ['email' => 'invalid'])->assertSessionHasErrors('email');
    }
    for ($i = 0; $i < 5; ++$i) {
        $this->post('/forgot-password', ['email' => 'neutral@example.com'])->assertRedirect('/forgot-password');
    }
    $this->post('/forgot-password', ['email' => 'neutral@example.com'])->assertStatus(429);
});
