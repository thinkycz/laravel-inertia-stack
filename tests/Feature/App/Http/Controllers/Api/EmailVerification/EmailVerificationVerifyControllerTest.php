<?php

declare(strict_types=1);

use App\Http\Controllers\Api\EmailVerification\EmailVerificationVerifyController;
use App\Models\User;
use Database\Factories\UserFactory;
use Thinkycz\LaravelCore\Notifications\EmailVerificationNotification;
use Thinkycz\LaravelCore\Services\EmailBrokerService;
use Thinkycz\LaravelCore\Support\Resolver;

\test('email can be verified with valid token', function (): void {
    $user = UserFactory::new()->unverified()->createOne();
    \expect($user)->toBeInstanceOf(User::class);

    $token = EmailBrokerService::inject()->store($user->getTable(), $user->getEmailForVerification());

    $data = [
        'email' => $user->getEmail(),
        'token' => $token,
    ];

    $response = $this->postJson(Resolver::resolveUrlGenerator()->action(EmailVerificationVerifyController::class), $data);

    $response->assertNoContent();

    $user->refresh();

    \expect($user->hasVerifiedEmail())->toBeTrue();
});

\test('already verified email returns no content', function (): void {
    $user = UserFactory::new()->createOne();
    \expect($user)->toBeInstanceOf(User::class);

    $token = EmailBrokerService::inject()->store($user->getTable(), $user->getEmailForVerification());

    $data = [
        'email' => $user->getEmail(),
        'token' => $token,
    ];

    $response = $this->postJson(Resolver::resolveUrlGenerator()->action(EmailVerificationVerifyController::class), $data);

    $response->assertNoContent();

    $user->refresh();

    \expect($user->hasVerifiedEmail())->toBeTrue();
});

\test('verification fails with invalid token', function (): void {
    $user = UserFactory::new()->unverified()->createOne();
    \expect($user)->toBeInstanceOf(User::class);

    $data = [
        'email' => $user->getEmail(),
        'token' => 'invalid-token',
    ];

    $response = $this->postJson(Resolver::resolveUrlGenerator()->action(EmailVerificationVerifyController::class), $data);

    $response->assertUnprocessable();

    $response->assertJsonValidationErrors(['token']);

    $user->refresh();

    \expect($user->hasVerifiedEmail())->toBeFalse();
});

\test('verification requires valid email', function (): void {
    $data = [
        'email' => 'invalid-email',
        'token' => 'any-token',
    ];

    $response = $this->postJson(Resolver::resolveUrlGenerator()->action(EmailVerificationVerifyController::class), $data);

    $response->assertUnprocessable();

    $response->assertJsonValidationErrors(['email']);
});

\test('verification requires token', function (): void {
    $data = [
        'email' => 'test@example.com',
        'token' => '',
    ];

    $response = $this->postJson(Resolver::resolveUrlGenerator()->action(EmailVerificationVerifyController::class), $data);

    $response->assertUnprocessable();

    $response->assertJsonValidationErrors(['token']);
});

\test('fixed verification token is never accepted and real token is consumed', function (): void {
    $user = UserFactory::new()->createOne();
    $user->markEmailAsUnverified();
    $broker = EmailBrokerService::inject();
    \expect($broker->validate('users', $user->getEmail(), '111111'))->toBeFalse();
    $token = $broker->store('users', $user->getEmail());
    $this->postJson('/api/v1/email_verification/verify', ['email' => $user->getEmail(), 'token' => $token])->assertNoContent();
    \expect($broker->validate('users', $user->getEmail(), $token))->toBeFalse();
    \expect($user->refresh()->hasVerifiedEmail())->toBeTrue();
});

\test('verification mail contains an absolute application link', function (): void {
    $mail = EmailVerificationNotification::inject('users', 'token', 'mail@example.com')
        ->locale('en')->toMail(null);
    \expect($mail->actionUrl)->toStartWith(Resolver::resolveUrlGenerator()->to('/email/verify') . '?');
});
