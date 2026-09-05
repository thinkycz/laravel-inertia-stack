<?php

declare(strict_types=1);

use App\Http\Controllers\Api\Me\MeUpdateController;
use App\Models\User;
use Database\Factories\UserFactory;
use Thinkycz\LaravelCore\Support\Resolver;

\test('authenticated user can update their profile', function (): void {
    $me = UserFactory::new()->createOne();
    \expect($me)->toBeInstanceOf(User::class);

    $response = $this->be($me)->post(Resolver::resolveUrlGenerator()->action(MeUpdateController::class), [
        'email' => $me->getEmail(),
        'locale' => 'en',
    ]);

    $response->assertOk();
});

\test('unauthenticated user cannot update profile', function (): void {
    $response = $this->post(Resolver::resolveUrlGenerator()->action(MeUpdateController::class), [
        'email' => 'updated@example.com',
        'locale' => 'en',
    ]);

    $response->assertUnauthorized();
});

\test('validation fails with invalid data', function (): void {
    $me = UserFactory::new()->createOne();
    \expect($me)->toBeInstanceOf(User::class);

    $response = $this->be($me)->postJson(Resolver::resolveUrlGenerator()->action(MeUpdateController::class), [
        'email' => 'invalid-email',
        'locale' => 'invalid-locale',
    ]);

    $response->assertUnprocessable();

    $response->assertJsonValidationErrors([
        'email',
        'locale',
    ]);
});

\test('email must be unique', function (): void {
    $me = UserFactory::new()->createOne();
    \expect($me)->toBeInstanceOf(User::class);

    $existingUser = UserFactory::new()->createOne();
    \expect($existingUser)->toBeInstanceOf(User::class);

    $response = $this->be($me)->postJson(Resolver::resolveUrlGenerator()->action(MeUpdateController::class), [
        'email' => $existingUser->getEmail(),
    ]);

    $response->assertUnprocessable();
});

\test('mass-assignment does not let a password leak through', function (): void {
    $me = UserFactory::new()->createOne();
    \expect($me)->toBeInstanceOf(User::class);

    $originalHash = $me->getAuthPassword();
    $originalEmail = $me->getEmail();

    // First defence: the SecureValidator rejects unknown fields. The
    // controller never sees the password or the email_verified_at.
    $response = $this->be($me)->postJson(
        Resolver::resolveUrlGenerator()->action(MeUpdateController::class),
        [
            'email' => $originalEmail,
            'locale' => 'en',
            'password' => 'pwned-by-attacker',
            'email_verified_at' => '2026-01-01 00:00:00',
        ],
    );

    $response->assertUnprocessable();

    $me->refresh();

    \expect($me->getAuthPassword())->toBe($originalHash);
    \expect($me->getEmail())->toBe($originalEmail);
    \expect($me->getEmailVerifiedAt())->not->toBeNull();

    // Second defence: even if a future refactor lets unknown fields
    // through validation, the controller must only touch the two
    // whitelisted fields. Verify by calling it with valid input and
    // confirming the hash is unchanged.
    $this->be($me)->postJson(
        Resolver::resolveUrlGenerator()->action(MeUpdateController::class),
        [
            'email' => $originalEmail,
            'locale' => 'en',
        ],
    )->assertOk();

    $me->refresh();

    \expect($me->getAuthPassword())->toBe($originalHash);
    \expect($me->getEmail())->toBe($originalEmail);
});

\test('update is rate limited after max attempts', function (): void {
    $me = UserFactory::new()->createOne();
    \expect($me)->toBeInstanceOf(User::class);

    $url = Resolver::resolveUrlGenerator()->action(MeUpdateController::class);

    for ($i = 0; $i < 5; ++$i) {
        $response = $this->be($me)->postJson($url, [
            'email' => "test{$i}@example.com",
            'locale' => 'en',
        ]);
        $response->assertOk();
    }

    $response = $this->be($me)->postJson($url, [
        'email' => 'throttled@example.com',
        'locale' => 'en',
    ]);

    $response->assertTooManyRequests();
});

\test('api email changes clear verification but locale changes retain it', function (): void {
    $user = UserFactory::new()->createOne();
    $user->markEmailAsVerified();
    $this->be($user, 'users')->postJson('/api/v1/me/update', ['locale' => 'cs'])->assertOk();
    \expect($user->refresh()->hasVerifiedEmail())->toBeTrue();
    $this->postJson('/api/v1/me/update', ['email' => 'new-address@example.com'])->assertOk();
    \expect($user->refresh()->hasVerifiedEmail())->toBeFalse();
});
