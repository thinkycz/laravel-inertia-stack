<?php

declare(strict_types=1);

use App\Http\Controllers\Api\Password\PasswordForgotController;
use Database\Factories\UserFactory;
use Illuminate\Support\Facades\Notification;
use Thinkycz\LaravelCore\Models\DatabaseToken;
use Thinkycz\LaravelCore\Notifications\PasswordResetNotification;
use Thinkycz\LaravelCore\Support\Config;
use Thinkycz\LaravelCore\Support\Resolver;

\test('user can request password reset link', function (): void {
    $user = UserFactory::new()->createOne([
        'email' => 'forgot@example.com',
    ]);
    \expect($user)->toBeInstanceOf(App\Models\User::class);

    $response = $this->postJson(Resolver::resolveUrlGenerator()->action(PasswordForgotController::class), [
        'email' => $user->getEmail(),
    ]);

    $response->assertNoContent();
});

\test('user can request password reset link reset link flow', function (): void {
    Notification::fake();

    Config::inject()->assign('auth.defaults.passwords', 'users');

    $user = UserFactory::new()->createOne([
        'email' => 'forgot@example.com',
    ]);
    \expect($user)->toBeInstanceOf(App\Models\User::class);

    $response = $this->postJson(Resolver::resolveUrlGenerator()->action(PasswordForgotController::class), [
        'email' => $user->getEmail(),
    ]);

    $response->assertNoContent();

    Notification::assertSentTo($user, PasswordResetNotification::class);
});

\test('reset link requests preserve credentials and sessions', function (): void {
    Notification::fake();

    Config::inject()->assign('auth.defaults.passwords', 'users');

    $user = UserFactory::new()->createOne([
        'email' => 'forgot@example.com',
    ]);
    \expect($user)->toBeInstanceOf(App\Models\User::class);

    $originalHash = $user->getAuthPassword();
    DatabaseToken::inject()->login($user->getTable(), $user);

    $response = $this->postJson(Resolver::resolveUrlGenerator()->action(PasswordForgotController::class), [
        'email' => $user->getEmail(),
    ]);

    $response->assertNoContent();

    Notification::assertSentTo($user, PasswordResetNotification::class);
    \expect($user->refresh()->getAuthPassword())->toBe($originalHash);
    $this->assertDatabaseCount('database_tokens', 1);
});

\test('password reset link request fails with invalid email', function (): void {
    $response = $this->postJson(Resolver::resolveUrlGenerator()->action(PasswordForgotController::class), [
        'email' => 'nonexistent@example.com',
    ]);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors('email');
});

\test('password reset link request requires email', function (): void {
    $response = $this->postJson(Resolver::resolveUrlGenerator()->action(PasswordForgotController::class));

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors('email');
});

\test('reset delivery quota is shared between api and web requests', function (): void {
    Notification::fake();
    $user = UserFactory::new()->createOne();
    $this->postJson('/api/v1/password/forgot', ['email' => $user->getEmail()])->assertNoContent();
    $this->postJson('/forgot-password', ['email' => $user->getEmail()])->assertStatus(429);
    Notification::assertSentToTimes($user, PasswordResetNotification::class, 1);
});
