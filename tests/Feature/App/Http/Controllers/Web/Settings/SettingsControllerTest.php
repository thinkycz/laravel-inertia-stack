<?php

declare(strict_types=1);

use App\Models\User;
use Database\Factories\UserFactory;
use Thinkycz\LaravelCore\Support\Resolver;
use Thinkycz\LaravelCore\Support\Typer;

\test('authenticated user can view settings page', function (): void {
    $user = Typer::assertInstance(UserFactory::new()->createOne(), User::class);

    $response = $this->be($user, 'users')->get('/settings', $this->inertiaHeaders());

    $response->assertOk();
    $response->assertJsonPath('component', 'settings/Index');
});

\test('guest is redirected from settings to login', function (): void {
    $response = $this->get('/settings');

    $response->assertRedirect('/login');
});

\test('user can update profile email and locale', function (): void {
    $user = Typer::assertInstance(UserFactory::new()->createOne(), User::class);

    $response = $this->be($user, 'users')->from('/settings')->post('/settings/profile', [
        'email' => 'new-email@example.com',
        'locale' => 'cs',
    ], $this->inertiaHeaders());

    // PRG: mutating POST redirects back; the success flash survives the
    // 302 → render chain via Inertia::flash().
    $response->assertRedirect('/settings');
    \assertInertiaFlash($response, 'success', \__('Profile updated.'));

    $user->refresh();
    static::assertSame('new-email@example.com', $user->getEmail());
    static::assertSame('cs', $user->getLocale());
});

\test('profile update rejects invalid email', function (): void {
    $user = Typer::assertInstance(UserFactory::new()->createOne(), User::class);
    $originalEmail = $user->getEmail();

    $response = $this->be($user, 'users')->post('/settings/profile', [
        'email' => 'not-an-email',
        'locale' => 'en',
    ]);

    $response->assertRedirect();
    $response->assertSessionHasErrors();

    $user->refresh();
    static::assertSame($originalEmail, $user->getEmail());
});

\test('profile update rejects email already in use', function (): void {
    $existing = Typer::assertInstance(UserFactory::new()->createOne([
        'email' => 'taken@example.com',
    ]), User::class);

    $user = Typer::assertInstance(UserFactory::new()->createOne(), User::class);

    $response = $this->be($user, 'users')->post('/settings/profile', [
        'email' => $existing->getEmail(),
        'locale' => 'en',
    ]);

    $response->assertRedirect();
    $response->assertSessionHasErrors();
});

\test('user can update their password', function (): void {
    $user = Typer::assertInstance(UserFactory::new()->createOne(), User::class);
    $originalHash = $user->getAuthPassword();

    $response = $this->be($user, 'users')->from('/settings')->post('/settings/password', [
        'password' => UserFactory::$password,
        'new_password' => 'new-password-123',
    ], $this->inertiaHeaders());

    // PRG: mutating POST redirects back; the success flash survives the
    // 302 → render chain via Inertia::flash().
    $response->assertRedirect('/settings');
    \assertInertiaFlash($response, 'success', \__('Password updated.'));

    $user->refresh();
    static::assertNotSame($originalHash, $user->getAuthPassword());
    static::assertTrue(Resolver::resolveHasher()->check('new-password-123', $user->getAuthPassword()));
});

\test('password update rejects wrong current password', function (): void {
    $user = Typer::assertInstance(UserFactory::new()->createOne(), User::class);
    $originalHash = $user->getAuthPassword();

    $response = $this->be($user, 'users')->post('/settings/password', [
        'password' => 'not-the-current-password',
        'new_password' => 'new-password-123',
    ]);

    $response->assertRedirect();
    $response->assertSessionHasErrors();

    $user->refresh();
    static::assertSame($originalHash, $user->getAuthPassword());
});

\test('email changes clear verification but locale changes preserve it', function (): void {
    $user = UserFactory::new()->createOne();
    $user->markEmailAsVerified();
    $this->be($user, 'users')->post('/settings/profile', ['email' => $user->getEmail(), 'locale' => 'cs'])->assertRedirect();
    \expect($user->refresh()->hasVerifiedEmail())->toBeTrue();
    $this->post('/settings/profile', ['email' => 'changed@example.com', 'locale' => 'cs'])->assertRedirect();
    \expect($user->refresh()->hasVerifiedEmail())->toBeFalse();
});

\test('invalid inertia settings returns to the unified settings form with session errors', function (): void {
    $user = UserFactory::new()->createOne();
    $this->be($user, 'users')->from('/settings')->post('/settings/profile', [
        'email' => 'invalid', 'locale' => 'en',
    ], $this->inertiaHeaders())->assertRedirect('/settings')->assertSessionHasErrors('email');
    $this->get('/settings', $this->inertiaHeaders())->assertJsonPath('component', 'settings/Index');
});
