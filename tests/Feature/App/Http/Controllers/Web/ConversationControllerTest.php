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
use Laravel\Ai\Models\ConversationMessage;
use RuntimeException;
use Thinkycz\LaravelCore\Support\Typer;

\test('guest is redirected to login from conversation endpoints', function (): void {
    $response = $this->get('/conversations/some-uuid');
    $response->assertRedirect('/login');

    $response = $this->post('/conversations', ['message' => 'hello']);
    $response->assertRedirect('/login');
});

\test('user can create a conversation and post first message', function (): void {
    $user = Typer::assertInstance(UserFactory::new()->createOne(), User::class);

    // Fake the ChatAgent responses (first is chat reply, second is generated title)
    ChatAgent::fake([
        'Hello, how can I help you?',
        'My Friendly Title',
    ]);

    $response = $this->be($user, 'users')
        ->post('/conversations', ['message' => 'hello']);

    // Execute the streamed content to trigger the title generation callback
    \ob_start();
    $response->sendContent();
    \ob_end_clean();

    // Should create the conversation and update its title
    $conversation = Conversation::where('user_id', $user->id)->first();
    \expect($conversation)->not->toBeNull();
    \expect($conversation->getAttribute('title'))->toBe('My Friendly Title');
    \expect(ConversationMessage::where('conversation_id', $conversation->getKey())->pluck('role')->all())
        ->toBe(['user', 'assistant']);

    $response->assertStatus(200);
    $response->assertHeader('X-Conversation-ID', Typer::assertString($conversation->getKey()));
});

\test('failed first message stream removes the empty conversation and emits an error event', function (): void {
    $user = Typer::assertInstance(UserFactory::new()->createOne(), User::class);

    ChatAgent::fake([
        static fn(): never => throw new RuntimeException('Provider failed'),
    ]);

    $response = $this->be($user, 'users')
        ->post('/conversations', ['message' => 'hello']);

    \ob_start();
    $response->sendContent();
    $content = Typer::assertString(\ob_get_clean());

    $response->assertStatus(200);
    \expect($content)->toContain('"type":"error"');
    \expect(Conversation::where('user_id', $user->getKey())->count())->toBe(0);
});

\test('user can append a message to an existing conversation', function (): void {
    $user = Typer::assertInstance(UserFactory::new()->createOne(), User::class);
    $conversation = Conversation::create([
        'id' => Str::uuid()->toString(),
        'user_id' => $user->getKey(),
        'title' => 'Test Conversation',
    ]);

    // Fake the ChatAgent response
    ChatAgent::fake(['Response to appended message']);

    $response = $this->be($user, 'users')
        ->post('/conversations/' . $conversation->getKey() . '/messages', ['message' => 'hello again']);

    $response->assertStatus(200);

    \ob_start();
    $response->sendContent();
    \ob_end_clean();

    \expect(ConversationMessage::where('conversation_id', $conversation->getKey())->pluck('role')->all())
        ->toBe(['user', 'assistant']);
});

\test('failed append stream does not corrupt existing conversation history', function (): void {
    $user = Typer::assertInstance(UserFactory::new()->createOne(), User::class);
    $conversation = Conversation::create([
        'id' => Str::uuid()->toString(),
        'user_id' => $user->getKey(),
        'title' => 'Test Conversation',
    ]);

    DB::table('agent_conversation_messages')->insert([
        'id' => Str::uuid()->toString(),
        'conversation_id' => $conversation->getKey(),
        'user_id' => $user->getKey(),
        'agent' => ChatAgent::class,
        'role' => 'user',
        'content' => 'existing',
        'attachments' => '[]',
        'tool_calls' => '[]',
        'tool_results' => '[]',
        'usage' => '[]',
        'meta' => '[]',
        'created_at' => \now(),
        'updated_at' => \now(),
    ]);

    ChatAgent::fake([
        static fn(): never => throw new RuntimeException('Provider failed'),
    ]);

    $response = $this->be($user, 'users')
        ->post('/conversations/' . $conversation->getKey() . '/messages', ['message' => 'hello again']);

    \ob_start();
    $response->sendContent();
    $content = Typer::assertString(\ob_get_clean());

    $response->assertStatus(200);
    \expect($content)->toContain('"type":"error"');
    \expect(Conversation::find($conversation->getKey()))->not->toBeNull();
    \expect(ConversationMessage::where('conversation_id', $conversation->getKey())->pluck('content')->all())
        ->toBe(['existing']);
});

\test('user can view conversation details', function (): void {
    $user = Typer::assertInstance(UserFactory::new()->createOne(), User::class);
    $conversation = Conversation::create([
        'id' => Str::uuid()->toString(),
        'user_id' => $user->getKey(),
        'title' => 'Test Conversation',
    ]);

    $response = $this->be($user, 'users')
        ->get('/conversations/' . $conversation->getKey(), $this->inertiaHeaders());

    $response->assertOk();
    $response->assertJsonPath('component', 'Dashboard');
    $response->assertJsonPath('props.conversation.id', $conversation->getKey());
    $response->assertJsonPath('props.conversation.title', 'Test Conversation');
});

\test('user cannot access another users conversation', function (): void {
    $owner = Typer::assertInstance(UserFactory::new()->createOne(), User::class);
    $otherUser = Typer::assertInstance(UserFactory::new()->createOne(), User::class);
    $conversation = Conversation::create([
        'id' => Str::uuid()->toString(),
        'user_id' => $owner->getKey(),
        'title' => 'Private Conversation',
    ]);

    $this->be($otherUser, 'users')
        ->get('/conversations/' . $conversation->getKey(), $this->inertiaHeaders())
        ->assertRedirect('/dashboard');

    $this->be($otherUser, 'users')
        ->post('/conversations/' . $conversation->getKey() . '/messages', ['message' => 'nope'])
        ->assertForbidden();

    $this->be($otherUser, 'users')
        ->delete('/conversations/' . $conversation->getKey())
        ->assertRedirect('/dashboard');

    \expect(Conversation::find($conversation->getKey()))->not->toBeNull();
});

\test('user is redirected to dashboard when deleting the active conversation', function (): void {
    $user = Typer::assertInstance(UserFactory::new()->createOne(), User::class);
    $conversation = Conversation::create([
        'id' => Str::uuid()->toString(),
        'user_id' => $user->getKey(),
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
    $conversation = Conversation::create([
        'id' => Str::uuid()->toString(),
        'user_id' => $user->getKey(),
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
        $table->foreignId('user_id')->nullable();
        $table->string('title');
        $table->timestamps();
    });

    Schema::create('custom_agent_messages', function ($table): void {
        $table->string('id', 36)->primary();
        $table->string('conversation_id', 36)->index();
        $table->foreignId('user_id')->nullable();
        $table->string('agent');
        $table->string('role', 25);
        $table->text('content');
        $table->text('attachments');
        $table->text('tool_calls');
        $table->text('tool_results');
        $table->text('usage');
        $table->text('meta');
        $table->timestamps();
    });

    $user = Typer::assertInstance(UserFactory::new()->createOne(), User::class);
    $conversation = Conversation::create([
        'id' => Str::uuid()->toString(),
        'user_id' => $user->getKey(),
        'title' => 'Custom Tables',
    ]);

    DB::table('custom_agent_messages')->insert([
        'id' => Str::uuid()->toString(),
        'conversation_id' => $conversation->getKey(),
        'user_id' => $user->getKey(),
        'agent' => ChatAgent::class,
        'role' => 'user',
        'content' => 'hello',
        'attachments' => '[]',
        'tool_calls' => '[]',
        'tool_results' => '[]',
        'usage' => '[]',
        'meta' => '[]',
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

\test('conversation creation is rate limited after max attempts', function (): void {
    $user = Typer::assertInstance(UserFactory::new()->createOne(), User::class);

    // Fake the ChatAgent response
    ChatAgent::fake(['Response']);

    for ($i = 0; $i < 30; ++$i) {
        $response = $this->be($user, 'users')
            ->post('/conversations', ['message' => 'hello']);
        $response->assertStatus(200);
    }

    $response = $this->be($user, 'users')
        ->post('/conversations', ['message' => 'hello']);
    $response->assertTooManyRequests();
});
