<?php

declare(strict_types=1);

use App\Models\User;
use Thinkycz\LaravelCore\Support\Resolver;

\test('guest can view register page', function (): void {
    $response = $this->get('/register', $this->inertiaHeaders());

    $response->assertOk();
    $response->assertJsonPath('component', 'auth/Register');
});

\test('user can register with database token cookie', function (): void {
    $response = $this->post('/register', [
        'email' => 'new-user@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'locale' => 'en',
    ]);

    $user = User::query()->where('email', 'new-user@example.com')->firstOrFail();

    $response->assertRedirect('/dashboard');
    $response->assertCookie(Resolver::resolveDatabaseTokenGuard($user->getTable())->cookieName());
    $this->assertDatabaseHas('users', [
        'email' => 'new-user@example.com',
        'locale' => 'en',
    ]);
});

\test('register rejects mismatched password confirmation', function (): void {
    $response = $this->post('/register', [
        'email' => 'new-user@example.com',
        'password' => 'password',
        'password_confirmation' => 'different-password',
        'locale' => 'en',
    ]);

    $response->assertRedirect();
    $response->assertSessionHasErrors();
    $this->assertDatabaseMissing('users', [
        'email' => 'new-user@example.com',
    ]);
});

\test('registered user password is hashed only once', function (): void {
    $this->post('/register', [
        'email' => 'new-user@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'locale' => 'en',
    ]);

    $user = User::query()->where('email', 'new-user@example.com')->firstOrFail();

    static::assertTrue(Resolver::resolveHasher()->check('password', $user->getAuthPassword()));
});
