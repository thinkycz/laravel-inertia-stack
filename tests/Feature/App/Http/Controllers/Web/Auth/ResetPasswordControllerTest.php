<?php

declare(strict_types=1);

use App\Models\User;
use Database\Factories\UserFactory;
use Illuminate\Support\Facades\Hash;
use Thinkycz\LaravelCore\Models\DatabaseToken;
use Thinkycz\LaravelCore\Support\Resolver;
use Thinkycz\LaravelCore\Support\Typer;

\test('guest can view reset password page with email and token', function (): void {
    $response = $this->get('/reset-password?email=user@example.com&token=abc', $this->inertiaHeaders());

    $response->assertOk();
    $response->assertJsonPath('component', 'auth/ResetPassword');
    $response->assertJsonPath('props.email', 'user@example.com');
    $response->assertJsonPath('props.token', 'abc');
});

\test('invalid token is rejected', function (): void {
    $user = Typer::assertInstance(UserFactory::new()->createOne(), User::class);

    $response = $this->post('/reset-password', [
        'email' => $user->getEmail(),
        'password' => 'new-password',
        'token' => 'invalid-token',
    ]);

    $response->assertRedirect();
    $response->assertSessionHasErrors();
});

\test('unknown email is rejected', function (): void {
    $response = $this->post('/reset-password', [
        'email' => 'nobody@example.com',
        'password' => 'new-password',
        'token' => 'some-token',
    ]);

    $response->assertRedirect();
    $response->assertSessionHasErrors();
});

\test('valid token updates password and logs user in', function (): void {
    $user = Typer::assertInstance(UserFactory::new()->createOne(), User::class);
    $originalHash = $user->getAuthPassword();

    $broker = Resolver::resolvePasswordBroker('users');
    $token = $broker->createToken($user);

    $response = $this->post('/reset-password', [
        'email' => $user->getEmail(),
        'password' => 'new-password',
        'token' => $token,
    ]);

    $response->assertRedirect('/dashboard');
    $response->assertCookie(Resolver::resolveDatabaseTokenGuard($user->getTable())->cookieName());

    $user->refresh();

    static::assertNotSame($originalHash, $user->getAuthPassword());
    static::assertTrue(Hash::check('new-password', $user->getAuthPassword()));
});

\test('successful reset revokes existing database tokens', function (): void {
    $user = Typer::assertInstance(UserFactory::new()->createOne(), User::class);

    DatabaseToken::inject()
        ->login($user->getTable(), $user);

    $this->assertDatabaseCount('database_tokens', 1);

    $broker = Resolver::resolvePasswordBroker('users');
    $token = $broker->createToken($user);

    $this->post('/reset-password', [
        'email' => $user->getEmail(),
        'password' => 'new-password',
        'token' => $token,
    ]);

    $user->refresh();

    static::assertNotEmpty($broker::INVALID_TOKEN);
});
