<?php

declare(strict_types=1);

use App\Jobs\PruneExpiredTokensJob;
use App\Models\User;
use Database\Factories\UserFactory;
use Illuminate\Support\Carbon;
use Thinkycz\LaravelCore\Models\DatabaseToken;
use Thinkycz\LaravelCore\Support\Config;
use Thinkycz\LaravelCore\Support\Resolver;
use Thinkycz\LaravelCore\Support\Typer;

\test('local database token cookie is not secure and uses lax same site', function (): void {
    Resolver::resolveApp()->detectEnvironment(static fn(): string => 'local');

    $user = UserFactory::new()->createOne();
    \expect($user)->toBeInstanceOf(User::class);
    $guard = Resolver::resolveDatabaseTokenGuard($user->getTable());

    $guard->login($user);

    $cookie = Resolver::resolveCookieJar()->queued($guard->cookieName(), null, '/');

    \expect($cookie)->not->toBeNull();
    \expect($cookie->isSecure())->toBeFalse();
    \expect($cookie->getSameSite())->toBe('lax');
    \expect($cookie->isHttpOnly())->toBeTrue();
});

\test('non local database token cookie is secure', function (): void {
    Resolver::resolveApp()->detectEnvironment(static fn(): string => 'staging');

    $user = UserFactory::new()->createOne();
    \expect($user)->toBeInstanceOf(User::class);
    $guard = Resolver::resolveDatabaseTokenGuard($user->getTable());

    $guard->login($user);

    $cookie = Resolver::resolveCookieJar()->queued($guard->cookieName(), null, '/');

    \expect($cookie)->not->toBeNull();
    \expect($cookie->getName())->toStartWith('__Host-');
    \expect($cookie->isSecure())->toBeTrue();
    \expect($cookie->getSameSite())->toBe('lax');
    \expect($cookie->isHttpOnly())->toBeTrue();
});

\test('new tokens have a configurable absolute expiry and matching cookie lifetime', function (): void {
    Config::inject()->assign('auth.token_lifetime_days', 30);
    $user = UserFactory::new()->createOne();
    $guard = Resolver::resolveDatabaseTokenGuard('users');
    $token = $guard->login($user);
    \expect($token->getExpiresAt()?->diffInSeconds(Carbon::now()->addDays(30), true))->toBeLessThan(2);
    $cookie = Resolver::resolveCookieJar()->queued($guard->cookieName(), null, '/');
    \expect($cookie?->getExpiresTime())->toBeGreaterThan(\time() + 29 * 86400)->toBeLessThanOrEqual(\time() + 30 * 86400);
    \expect($token->getLastUsedAt())->not->toBeNull();
});

\test('expired bearer credentials cannot authenticate', function (): void {
    $user = UserFactory::new()->createOne();
    $token = DatabaseToken::inject()->login('users', $user);
    $token->forceFill(['expires_at' => Carbon::now()->subSecond()])->save();
    \expect(DatabaseToken::inject()->findByBearer(Typer::assertString($token->bearer)))->toBeNull();
    $this->withToken(Typer::assertString($token->bearer))->getJson('/api/v1/me/show')->assertUnauthorized();
});

\test('expired cookie credentials redirect to login', function (): void {
    $user = UserFactory::new()->createOne();
    $token = DatabaseToken::inject()->login('users', $user);
    $token->forceFill(['expires_at' => Carbon::now()->subSecond()])->save();
    $cookieName = Resolver::resolveDatabaseTokenGuard('users')->cookieName();
    $this->withCookie($cookieName, Typer::assertString($token->bearer))->get('/dashboard')->assertRedirect('/login');
});

\test('expired token pruning preserves active sessions', function (): void {
    $user = UserFactory::new()->createOne();
    $active = DatabaseToken::inject()->login('users', $user);
    $expired = DatabaseToken::inject()->login('users', $user);
    $expired->forceFill(['expires_at' => Carbon::now()->subSecond()])->save();
    (new PruneExpiredTokensJob())->handle();
    $this->assertDatabaseHas('database_tokens', ['id' => $active->getKey()]);
    $this->assertDatabaseMissing('database_tokens', ['id' => $expired->getKey()]);
});
