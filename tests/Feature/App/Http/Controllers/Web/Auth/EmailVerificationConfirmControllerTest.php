<?php

declare(strict_types=1);

use App\Models\User;
use Database\Factories\UserFactory;
use Illuminate\Auth\Events\Verified;
use Illuminate\Support\Facades\Event;
use Thinkycz\LaravelCore\Services\EmailBrokerService;
use Thinkycz\LaravelCore\Support\Typer;

\test('valid token marks user as verified and redirects to dashboard', function (): void {
    Event::fake();

    $user = Typer::assertInstance(UserFactory::new()->unverified()->createOne([
        'email' => 'unverified@example.com',
    ]), User::class);

    $token = EmailBrokerService::inject()->store($user->getTable(), $user->getEmailForVerification());

    $response = $this->be($user, 'users')->get('/email/verify?' . \http_build_query([
        'guard' => $user->getTable(),
        'email' => $user->getEmailForVerification(),
        'token' => $token,
    ]));

    $response->assertRedirect('/dashboard');
    \assertInertiaFlash($response, 'success', \__('Email verified.'));

    $user->refresh();
    static::assertNotNull($user->getEmailVerifiedAt());

    Event::assertDispatched(Verified::class);
});

\test('valid token redirects to login for unauthenticated visitor', function (): void {
    Event::fake();

    $user = Typer::assertInstance(UserFactory::new()->unverified()->createOne([
        'email' => 'unverified@example.com',
    ]), User::class);

    $token = EmailBrokerService::inject()->store($user->getTable(), $user->getEmailForVerification());

    $response = $this->get('/email/verify?' . \http_build_query([
        'guard' => $user->getTable(),
        'email' => $user->getEmailForVerification(),
        'token' => $token,
    ]));

    $response->assertRedirect('/login');
    \assertInertiaFlash($response, 'success', \__('Email verified.'));

    $user->refresh();
    static::assertNotNull($user->getEmailVerifiedAt());

    Event::assertDispatched(Verified::class);
});

\test('already verified user is redirected to dashboard with idempotent message', function (): void {
    Event::fake();

    $user = Typer::assertInstance(UserFactory::new()->createOne([
        'email' => 'verified@example.com',
    ]), User::class);

    $token = EmailBrokerService::inject()->store($user->getTable(), $user->getEmailForVerification());

    $response = $this->be($user, 'users')->get('/email/verify?' . \http_build_query([
        'guard' => $user->getTable(),
        'email' => $user->getEmailForVerification(),
        'token' => $token,
    ]));

    $response->assertRedirect('/dashboard');
    \assertInertiaFlash($response, 'success', \__('Email already verified.'));

    Event::assertNotDispatched(Verified::class);
});

\test('invalid token redirects to login with error', function (): void {
    Typer::assertInstance(UserFactory::new()->unverified()->createOne([
        'email' => 'unverified@example.com',
    ]), User::class);

    $response = $this->get('/email/verify?' . \http_build_query([
        'guard' => 'users',
        'email' => 'unverified@example.com',
        'token' => 'not-a-real-token',
    ]));

    $response->assertRedirect('/login');
    \assertInertiaFlash($response, 'error', \__('The verification link is invalid or has expired.'));
});

\test('unknown email redirects to login with error', function (): void {
    $response = $this->get('/email/verify?' . \http_build_query([
        'guard' => 'users',
        'email' => 'nobody@example.com',
        'token' => 'any-token',
    ]));

    $response->assertRedirect('/login');
    \assertInertiaFlash($response, 'error', \__('We can\'t find a user with that email address.'));
});

\test('missing parameters return 422', function (): void {
    $response = $this->get('/email/verify');

    $response->assertRedirect();
    $response->assertSessionHasErrors();
});

\test('guard field is whitelisted to known guards', function (): void {
    $user = Typer::assertInstance(UserFactory::new()->unverified()->createOne([
        'email' => 'unverified@example.com',
    ]), User::class);

    $token = EmailBrokerService::inject()->store($user->getTable(), $user->getEmailForVerification());

    // A bogus guard must be rejected by the validator (it was a
    // free-form varchar in the original implementation; the fix
    // narrows it to GuardEnum::values()).
    $response = $this->get('/email/verify?' . \http_build_query([
        'guard' => 'something-rogue',
        'email' => $user->getEmailForVerification(),
        'token' => $token,
    ]));

    $response->assertRedirect();
    $response->assertSessionHasErrors();
});

\test('verification tokens expire after three hours and have no shared bypass value', function (): void {
    $broker = EmailBrokerService::inject();
    $token = $broker->store('users', 'ttl@example.com');
    \expect($broker->validate('users', 'ttl@example.com', '111111'))->toBeFalse();
    $this->travel(179)->minutes();
    \expect($broker->validate('users', 'ttl@example.com', $token))->toBeTrue();
    $this->travel(2)->minutes();
    \expect($broker->validate('users', 'ttl@example.com', $token))->toBeFalse();
});
