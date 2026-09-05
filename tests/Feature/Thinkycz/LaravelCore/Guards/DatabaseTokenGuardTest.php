<?php

declare(strict_types=1);

use App\Models\User;
use Database\Factories\UserFactory;
use Thinkycz\LaravelCore\Support\Resolver;

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
