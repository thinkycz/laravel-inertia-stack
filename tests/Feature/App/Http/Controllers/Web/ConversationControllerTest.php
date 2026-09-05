<?php

declare(strict_types=1);

namespace Tests\Feature\App\Http\Controllers\Web;

use App\Ai\Agents\ChatAgent;
use App\Models\User;
use Database\Factories\UserFactory;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Laravel\Ai\Models\Conversation;
use Thinkycz\LaravelCore\Support\Typer;

\beforeEach(function (): void {
    $this->withSession(['_token' => 'conversation-test-token'])
        ->withHeader('X-CSRF-TOKEN', 'conversation-test-token');
});

\test('guest is redirected to login from conversation show endpoint', function (): void {
    $response = $this->get('/conversations/some-uuid');
    $response->assertRedirect('/login');
});

\test('user can view conversation details', function (): void {
    $user = Typer::assertInstance(UserFactory::new()->createOne(), User::class);
    $conversation = $user->conversations()->create([
        'id' => Str::uuid()->toString(),
        'title' => 'Test Conversation',
    ]);

    $response = $this->be($user, 'users')
        ->get('/conversations/' . $conversation->getKey(), $this->inertiaHeaders());

    $response->assertOk();
    $response->assertJsonPath('component', 'Dashboard');
    $response->assertJsonPath('props.conversation.id', $conversation->getKey());
    $response->assertJsonPath('props.conversation.title', 'Test Conversation');
});

\test('conversation details hydrate persisted approval tool parts', function (): void {
    $user = Typer::assertInstance(UserFactory::new()->createOne(), User::class);
    $conversation = $user->conversations()->create([
        'id' => Str::uuid()->toString(),
        'title' => 'Pending Approval',
    ]);

    $conversation->messages()->create([
        'id' => Str::uuid()->toString(),
        'participant_type' => $user->getMorphClass(),
        'participant_id' => $user->getKey(),
        'agent' => ChatAgent::class,
        'role' => 'assistant',
        'content' => '',
        'attachments' => [],
        'tool_calls' => [[
            'id' => 'notification-call',
            'name' => 'SendTestNotificationTool',
            'arguments' => ['subject' => 'Subject', 'message' => 'Message'],
        ]],
        'tool_results' => [],
        'usage' => [],
        'meta' => [],
        'approval_state' => ['pending' => ['notification-call' => null]],
    ]);

    $response = $this->be($user, 'users')
        ->get('/conversations/' . $conversation->getKey(), $this->inertiaHeaders());

    $response->assertOk();
    $response->assertJsonPath('props.conversation.messages.0.parts.0.type', 'tool-SendTestNotificationTool');
    $response->assertJsonPath('props.conversation.messages.0.parts.0.state', 'approval-requested');
    $response->assertJsonPath('props.conversation.messages.0.parts.0.input.subject', 'Subject');
});

\test('user cannot access another users conversation', function (): void {
    $owner = Typer::assertInstance(UserFactory::new()->createOne(), User::class);
    $otherUser = Typer::assertInstance(UserFactory::new()->createOne(), User::class);
    $conversation = $owner->conversations()->create([
        'id' => Str::uuid()->toString(),
        'title' => 'Private Conversation',
    ]);

    $this->be($otherUser, 'users')
        ->get('/conversations/' . $conversation->getKey(), $this->inertiaHeaders())
        ->assertRedirect('/dashboard');

    $this->be($otherUser, 'users')
        ->delete('/conversations/' . $conversation->getKey())
        ->assertRedirect('/dashboard');

    \expect(Conversation::find($conversation->getKey()))->not->toBeNull();
});

\test('user is redirected to dashboard when deleting the active conversation', function (): void {
    $user = Typer::assertInstance(UserFactory::new()->createOne(), User::class);
    $conversation = $user->conversations()->create([
        'id' => Str::uuid()->toString(),
        'title' => 'Test Conversation',
    ]);

    $response = $this->be($user, 'users')
        ->from('/conversations/' . $conversation->getKey())
        ->delete('/conversations/' . $conversation->getKey());

    $response->assertRedirect('/dashboard');
    \expect(Conversation::find($conversation->getKey()))->toBeNull();
});

\test('user is redirected back when deleting a non-active conversation', function (): void {
    $user = Typer::assertInstance(UserFactory::new()->createOne(), User::class);
    $conversation = $user->conversations()->create([
        'id' => Str::uuid()->toString(),
        'title' => 'Test Conversation',
    ]);

    $response = $this->be($user, 'users')
        ->from('/conversations/some-other-active-uuid')
        ->delete('/conversations/' . $conversation->getKey());

    $response->assertRedirect('/conversations/some-other-active-uuid');
    \expect(Conversation::find($conversation->getKey()))->toBeNull();
});

\test('conversation deletion uses configured ai message table names', function (): void {
    Config::set('ai.conversations.tables.conversations', 'custom_agent_conversations');
    Config::set('ai.conversations.tables.messages', 'custom_agent_messages');

    Schema::create('custom_agent_conversations', function ($table): void {
        $table->string('id', 36)->primary();
        $table->string('participant_type')->nullable();
        $table->unsignedBigInteger('participant_id')->nullable();
        $table->string('title');
        $table->timestamps();
    });

    Schema::create('custom_agent_messages', function ($table): void {
        $table->string('id', 36)->primary();
        $table->string('conversation_id', 36)->index();
        $table->string('participant_type')->nullable();
        $table->unsignedBigInteger('participant_id')->nullable();
        $table->string('agent');
        $table->string('role', 25);
        $table->text('content');
        $table->text('attachments');
        $table->text('tool_calls');
        $table->text('tool_results');
        $table->text('usage');
        $table->text('meta');
        $table->text('approval_state')->nullable();
        $table->timestamps();
    });

    $user = Typer::assertInstance(UserFactory::new()->createOne(), User::class);
    $conversation = $user->conversations()->create([
        'id' => Str::uuid()->toString(),
        'title' => 'Custom Tables',
    ]);

    DB::table('custom_agent_messages')->insert([
        'id' => Str::uuid()->toString(),
        'conversation_id' => $conversation->getKey(),
        'participant_type' => $user->getMorphClass(),
        'participant_id' => $user->getKey(),
        'agent' => 'agent',
        'role' => 'user',
        'content' => 'hello',
        'attachments' => '[]',
        'tool_calls' => '[]',
        'tool_results' => '[]',
        'usage' => '[]',
        'meta' => '[]',
        'approval_state' => null,
        'created_at' => \now(),
        'updated_at' => \now(),
    ]);

    $this->be($user, 'users')
        ->from('/conversations/' . $conversation->getKey())
        ->delete('/conversations/' . $conversation->getKey())
        ->assertRedirect('/dashboard');

    \expect(DB::table('custom_agent_messages')->count())->toBe(0);
    \expect(Conversation::find($conversation->getKey()))->toBeNull();

    Config::set('ai.conversations.tables.conversations', 'agent_conversations');
    Config::set('ai.conversations.tables.messages', 'agent_conversation_messages');
});
