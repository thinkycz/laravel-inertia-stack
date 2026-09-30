<?php

declare(strict_types=1);

use App\Ai\Agents\ChatAgent;
use App\Models\User;
use Database\Factories\UserFactory;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Thinkycz\LaravelCore\Models\DatabaseToken;
use Thinkycz\LaravelCore\Notifications\EmailVerificationNotification;
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
    $response->assertRedirect('/login');
    \assertInertiaFlash($response, 'success', \__('security.password_updated'));

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

\test('changing email clears verification and queues a verification notification', function (): void {
    Notification::fake();
    $user = UserFactory::new()->createOne();
    \expect($user->hasVerifiedEmail())->toBeTrue();
    $this->be($user, 'users')->post('/settings/profile', ['email' => 'changed@example.com', 'locale' => 'en'])->assertRedirect();
    \expect($user->refresh()->hasVerifiedEmail())->toBeFalse();
    Notification::assertSentTo($user, EmailVerificationNotification::class);
});

\test('changing only the locale preserves verification', function (): void {
    Notification::fake();
    $user = UserFactory::new()->createOne();
    $this->be($user, 'users')->post('/settings/profile', ['email' => $user->getEmail(), 'locale' => 'sk'])->assertRedirect();
    \expect($user->refresh()->hasVerifiedEmail())->toBeTrue();
    Notification::assertNothingSent();
});

\test('session list exposes only owned active metadata and marks the current session', function (): void {
    $user = UserFactory::new()->createOne();
    $current = Resolver::resolveDatabaseTokenGuard('users')->login($user);
    $other = DatabaseToken::inject()->login('users', $user);
    $expired = DatabaseToken::inject()->login('users', $user);
    $expired->forceFill(['expires_at' => Carbon::now()->subMinute()])->save();
    DatabaseToken::inject()->login('users', UserFactory::new()->createOne());
    $response = $this->get('/settings/sessions', $this->inertiaHeaders());
    $response->assertOk()->assertJsonCount(2, 'props.sessions');
    $sessions = $response->json('props.sessions');
    \expect(\array_column($sessions, 'id'))->toContain($current->getKey(), $other->getKey());
    \expect(\array_column($sessions, 'current'))->toContain(true, false);
    \expect(\array_keys($sessions[0]))->not->toContain('hash', 'bearer');
});

\test('revoking an owned session requires the correct password', function (): void {
    $user = UserFactory::new()->createOne();
    Resolver::resolveDatabaseTokenGuard('users')->login($user);
    $other = DatabaseToken::inject()->login('users', $user);
    $url = '/settings/sessions/' . $other->getKey() . '/revoke';
    $this->post($url, ['password' => 'incorrect-password'])->assertSessionHasErrors('password');
    $this->assertDatabaseHas('database_tokens', ['id' => $other->getKey()]);
    $this->post($url, ['password' => UserFactory::$password])->assertRedirect('/settings#security');
    $this->assertDatabaseMissing('database_tokens', ['id' => $other->getKey()]);
});

\test('sessions cannot be revoked across accounts or through the current-session endpoint', function (): void {
    $user = UserFactory::new()->createOne();
    $current = Resolver::resolveDatabaseTokenGuard('users')->login($user);
    $foreign = DatabaseToken::inject()->login('users', UserFactory::new()->createOne());
    $this->post('/settings/sessions/' . $foreign->getKey() . '/revoke', ['password' => UserFactory::$password])->assertNotFound();
    $this->post('/settings/sessions/' . $current->getKey() . '/revoke', ['password' => UserFactory::$password])->assertUnprocessable();
    $this->assertDatabaseCount('database_tokens', 2);
});

\test('account deletion confirms the password and removes only owned data', function (): void {
    $user = UserFactory::new()->createOne();
    $foreign = UserFactory::new()->createOne();
    Resolver::resolveDatabaseTokenGuard('users')->login($user);
    DatabaseToken::inject()->login('users', $foreign);
    $ownedConversation = $user->conversations()->create(['id' => Str::uuid()->toString(), 'title' => 'Owned']);
    $foreignConversation = $foreign->conversations()->create(['id' => Str::uuid()->toString(), 'title' => 'Keep']);
    $ownedConversation->messages()->create(['id' => Str::uuid()->toString(), 'participant_type' => $user->getMorphClass(), 'participant_id' => $user->getKey(), 'agent' => ChatAgent::class, 'role' => 'user', 'content' => 'Hello', 'attachments' => [], 'steps' => [], 'status' => 'completed', 'usage' => [], 'meta' => []]);
    $this->post('/settings/delete', ['password' => 'wrong-password'])->assertSessionHasErrors('password');
    $this->assertDatabaseCount('users', 2);
    $this->post('/settings/delete', ['password' => UserFactory::$password])->assertRedirect('/login');
    $this->assertDatabaseMissing('users', ['id' => $user->getKey()]);
    $this->assertDatabaseHas('users', ['id' => $foreign->getKey()]);
    $this->assertDatabaseCount('database_tokens', 1);
    $this->assertDatabaseMissing('agent_conversations', ['id' => $ownedConversation->getKey()]);
    $this->assertDatabaseHas('agent_conversations', ['id' => $foreignConversation->getKey()]);
    $this->assertDatabaseCount('agent_conversation_messages', 0);
});

\test('account deletion removes outstanding password-reset credentials', function (): void {
    $user = UserFactory::new()->createOne();
    Resolver::resolvePasswordBroker('users')->createToken($user);
    $this->assertDatabaseHas('user_password_resets', ['email' => $user->getEmail()]);
    $this->be($user, 'users')->post('/settings/delete', ['password' => UserFactory::$password])->assertRedirect('/login');
    $this->assertDatabaseMissing('user_password_resets', ['email' => $user->getEmail()]);
});
