<?php

declare(strict_types=1);

use App\Models\User;
use Database\Factories\UserFactory;
use Illuminate\Http\Request;
use Thinkycz\LaravelCore\Support\Typer;

\test('share returns guest props when unauthenticated', function (): void {
    $middleware = new App\Http\Middleware\HandleInertiaRequests();

    $request = Request::create('/login', 'GET');

    /** @var SharedProps $shared */
    $shared = $middleware->share($request);

    static::assertArrayHasKey('app', $shared);
    static::assertSame('Starter Tests', $shared['app']['name']);
    static::assertArrayHasKey('auth', $shared);
    static::assertArrayHasKey('flash', $shared);
    static::assertArrayHasKey('errors', $shared);
});

\test('share resolves user to null for guest', function (): void {
    $middleware = new App\Http\Middleware\HandleInertiaRequests();

    $request = Request::create('/login', 'GET');

    /** @var SharedProps $shared */
    $shared = $middleware->share($request);

    $authUser = ($shared['auth']['user'])();

    static::assertNull($authUser);
});

\test('share resolves user to array for authenticated', function (): void {
    $user = Typer::assertInstance(UserFactory::new()->createOne(), User::class);

    $this->be($user, 'users');

    $middleware = new App\Http\Middleware\HandleInertiaRequests();

    $request = Request::create('/dashboard', 'GET');

    /** @var SharedProps $shared */
    $shared = $middleware->share($request);

    $authUser = ($shared['auth']['user'])();

    static::assertNotNull($authUser);
    static::assertSame($user->getEmail(), $authUser['email']);
    static::assertSame($user->getKey(), $authUser['id']);
});

\test('share resolves flash to session values', function (): void {
    $this->session(['success' => 'Welcome back', 'error' => 'Something failed']);

    $middleware = new App\Http\Middleware\HandleInertiaRequests();

    $request = Request::create('/dashboard', 'GET');
    $request->setLaravelSession($this->app['session.store']);

    /** @var SharedProps $shared */
    $shared = $middleware->share($request);

    static::assertSame('Welcome back', ($shared['flash']['success'])());
    static::assertSame('Something failed', ($shared['flash']['error'])());
});
