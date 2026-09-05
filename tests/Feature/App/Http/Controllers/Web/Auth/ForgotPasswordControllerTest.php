<?php

declare(strict_types=1);

use App\Models\User;
use Database\Factories\UserFactory;
use Illuminate\Support\Facades\Notification;
use Thinkycz\LaravelCore\Models\DatabaseToken;
use Thinkycz\LaravelCore\Notifications\PasswordResetNotification;
use Thinkycz\LaravelCore\Support\Typer;

\test('guest can view forgot password page', function (): void {
    $response = $this->get('/forgot-password', $this->inertiaHeaders());

    $response->assertOk();
    $response->assertJsonPath('component', 'auth/ForgotPassword');
});

\test('unknown email returns validation error', function (): void {
    $response = $this->post('/forgot-password', [
        'email' => 'nobody@example.com',
    ]);

    $response->assertRedirect();
    $response->assertSessionHasErrors();
});

\test('known email sends a reset link without changing the password', function (): void {
    Notification::fake();

    $user = Typer::assertInstance(UserFactory::new()->createOne(), User::class);
    $originalHash = $user->getAuthPassword();

    $response = $this->post('/forgot-password', [
        'email' => $user->getEmail(),
    ], $this->inertiaHeaders());

    $response->assertOk();
    $response->assertJsonPath('component', 'auth/ForgotPassword');
    \assertInertiaFlash($response, 'success', \__('A password reset link has been sent to your email address.'));

    $user->refresh();

    static::assertSame($originalHash, $user->getAuthPassword());

    Notification::assertSentTo($user, PasswordResetNotification::class);
});

\test('known email preserves existing database tokens', function (): void {
    $user = Typer::assertInstance(UserFactory::new()->createOne(), User::class);

    DatabaseToken::inject()
        ->login($user->getTable(), $user);

    $this->assertDatabaseCount('database_tokens', 1);

    $this->post('/forgot-password', [
        'email' => $user->getEmail(),
    ]);

    $this->assertDatabaseCount('database_tokens', 1);
});
