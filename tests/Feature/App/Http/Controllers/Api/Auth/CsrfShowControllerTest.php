<?php

declare(strict_types=1);

use Database\Factories\UserFactory;
use Thinkycz\LaravelCore\Models\DatabaseToken;
use Thinkycz\LaravelCore\Support\Resolver;

\test('csrf bootstrap initializes encrypted session and readable xsrf cookies', function (): void {
    $this->getJson('/api/v1/auth/csrf')->assertNoContent()
        ->assertCookie('XSRF-TOKEN')
        ->assertCookie(Resolver::resolveSessionManager()->getName());
});

\test('cookie mutations and arbitrary bearer headers require csrf protection', function (): void {
    $this->app->instance('env', 'local');

    $this->postJson('/api/v1/auth/login', [])->assertStatus(419);
    $this->withHeader('Authorization', 'Bearer invalid')->postJson('/api/v1/auth/register', [])->assertStatus(419);
});

\test('a valid explicit bearer authenticates api mutation without csrf', function (): void {
    $user = UserFactory::new()->createOne();
    $token = DatabaseToken::inject()->login('users', $user);
    $this->app->instance('env', 'local');

    $this->withToken($token->bearer)->postJson('/api/v1/me/update', ['locale' => 'cs'])->assertOk();
    \expect($user->refresh()->getLocale())->toBe('cs');
});

\test('api login cookies authenticate the web surface', function (): void {
    $user = UserFactory::new()->createOne();
    $response = $this->postJson('/api/v1/auth/login', [
        'email' => $user->getEmail(),
        'password' => UserFactory::$password,
    ])->assertOk();
    $cookieName = Resolver::resolveDatabaseTokenGuard('users')->cookieName();
    $cookie = $response->getCookie($cookieName, false);
    \expect($cookie)->not->toBeNull();
    Resolver::resolveAuthManager()->forgetGuards();

    $this->withUnencryptedCookie($cookieName, $cookie->getValue())->get('/dashboard')->assertOk();
});

\test('cross-origin cookie-authenticated deletion fails without request-forgery token', function (): void {
    $user = UserFactory::new()->createOne();
    $response = $this->postJson('/api/v1/auth/login', ['email' => $user->getEmail(), 'password' => UserFactory::$password])->assertOk();
    $cookieName = Resolver::resolveDatabaseTokenGuard('users')->cookieName();
    $cookie = $response->getCookie($cookieName, false);
    Resolver::resolveAuthManager()->forgetGuards();
    $this->app->instance('env', 'local');
    $this->withUnencryptedCookie($cookieName, $cookie->getValue())->withHeaders([
        'Origin' => 'https://untrusted.example', 'Sec-Fetch-Site' => 'cross-site',
    ])->postJson('/api/v1/me/destroy')->assertStatus(419);
    $this->assertDatabaseHas('users', ['id' => $user->getKey()]);
});
