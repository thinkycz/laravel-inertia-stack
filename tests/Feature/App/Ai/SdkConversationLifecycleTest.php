<?php

declare(strict_types=1);

use App\Ai\Agents\ChatAgent;
use App\Ai\ConversationRepository;
use Database\Factories\UserFactory;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Laravel\Ai\Ai;
use Laravel\Ai\Models\ConversationMessage;
use Laravel\Ai\Responses\Data\ToolCall;
use Tests\Feature\App\Ai\SdkLifecycleTextGateway;
use Thinkycz\LaravelCore\Support\Resolver;

\beforeEach(function (): void {
    Http::preventStrayRequests();
    Notification::fake();
    $this->withSession(['_token' => 'sdk-lifecycle-csrf'])
        ->withHeader('X-CSRF-TOKEN', 'sdk-lifecycle-csrf');
});

\test('SDK remembering owns exactly one pair per normal turn and context appends the prompt once', function (): void {
    $gateway = new SdkLifecycleTextGateway(['First answer', 'Second answer']);
    Ai::textProvider()->useTextGateway($gateway->preventStrayPrompts());
    $user = UserFactory::new()->createOne();
    $this->be($user, 'users');

    $first = $this->postJson('/agent/chat', ['message' => 'First question'])->assertOk();
    $id = $first->headers->get('x-conversation-id');
    $first->streamedContent();
    $this->assertDatabaseCount('agent_conversations', 1);
    $this->assertDatabaseCount('agent_conversation_messages', 2);
    \expect(ConversationMessage::query()->orderBy('id')->pluck('content')->all())
        ->toBe(['First question', 'First answer']);

    $second = $this->postJson('/agent/chat', ['message' => 'Second question', 'conversation_id' => $id])->assertOk();
    $second->streamedContent();
    $second->assertHeader('x-conversation-id', $id);
    $this->assertDatabaseCount('agent_conversations', 1);
    $this->assertDatabaseCount('agent_conversation_messages', 4);
    \expect($gateway->contexts)->toBe([
        ['First question'],
        ['First question', 'First answer', 'Second question'],
    ]);
});

\test('unknown conversation does not create a replacement or invoke a provider', function (): void {
    $gateway = new SdkLifecycleTextGateway(['Never']);
    Ai::textProvider()->useTextGateway($gateway->preventStrayPrompts());
    $this->be(UserFactory::new()->createOne(), 'users')
        ->postJson('/agent/chat', ['message' => 'Question', 'conversation_id' => 'missing'])
        ->assertNotFound();
    $this->assertDatabaseCount('agent_conversations', 0);
    $this->assertDatabaseCount('agent_conversation_messages', 0);
    \expect($gateway->contexts)->toBe([]);
});

\test('SDK context and rendered history independently retain the latest hundred messages', function (): void {
    $gateway = new SdkLifecycleTextGateway(['New answer']);
    Ai::textProvider()->useTextGateway($gateway->preventStrayPrompts());
    $user = UserFactory::new()->createOne();
    $conversation = $user->conversations()->create(['id' => 'history-window', 'title' => 'History']);
    for ($index = 0; $index < 110; ++$index) {
        $conversation->messages()->create([
            'id' => \sprintf('%036d', $index),
            'participant_type' => $user->getMorphClass(),
            'participant_id' => $user->getKey(),
            'agent' => ChatAgent::class,
            'role' => $index % 2 === 0 ? 'user' : 'assistant',
            'content' => 'Message ' . $index,
            'attachments' => [], 'tool_calls' => [], 'tool_results' => [], 'usage' => [], 'meta' => [],
        ]);
    }
    $payload = Resolver::resolve(ConversationRepository::class)->dashboardPayload($conversation);
    \expect($payload['messages'])->toHaveCount(100);
    \expect($payload['messages'][0]['parts'][0]['text'])->toBe('Message 10');
    \expect($payload['messages'][99]['parts'][0]['text'])->toBe('Message 109');

    $response = $this->be($user, 'users')->postJson('/agent/chat', [
        'message' => 'Current question', 'conversation_id' => $conversation->getKey(),
    ])->assertOk();
    $response->streamedContent();
    \expect($gateway->contexts[0])->toHaveCount(101);
    \expect($gateway->contexts[0][0])->toBe('Message 10');
    \expect($gateway->contexts[0][99])->toBe('Message 109');
    \expect($gateway->contexts[0][100])->toBe('Current question');
});

\test('two hundred native SDK text deltas use fewer than one hundred SQL statements', function (): void {
    $text = \implode(' ', \array_fill(0, 200, 'delta'));
    Ai::textProvider()->useTextGateway((new SdkLifecycleTextGateway([$text]))->preventStrayPrompts());
    $this->be(UserFactory::new()->createOne(), 'users');
    $connection = DB::connection();
    $connection->flushQueryLog();
    $connection->enableQueryLog();
    try {
        $response = $this->postJson('/agent/chat', ['message' => 'Question'])->assertOk();
        $body = $response->streamedContent();
        $queries = \count($connection->getQueryLog());
    } finally {
        $connection->disableQueryLog();
    }
    \expect(\mb_substr_count($body, '"type":"text-delta"'))->toBe(200);
    \expect($queries)->toBeLessThan(100);
    $this->assertDatabaseCount('agent_conversation_messages', 2);
    \expect(ConversationMessage::query()->where('role', 'assistant')->value('content'))->toBe($text);
});

\test('native approval events omit null reasons for the AI SDK client contract', function (): void {
    Ai::textProvider()->useTextGateway((new SdkLifecycleTextGateway([
        new ToolCall('notification-without-reason', 'SendTestNotificationTool', [
            'subject' => 'Review this subject', 'message' => 'Review this body',
        ]),
    ]))->preventStrayPrompts());
    $response = $this->be(UserFactory::new()->createOne(), 'users')
        ->postJson('/agent/chat', ['message' => 'Draft a notification'])->assertOk();
    $approvals = [];
    foreach (\explode("\n", $response->streamedContent()) as $line) {
        if (!\str_starts_with($line, 'data: {')) {
            continue;
        }
        $event = \json_decode(\mb_substr($line, 6), true, 512, \JSON_THROW_ON_ERROR);
        if ($event['type'] === 'tool-approval-request') {
            $approvals[] = $event;
        }
    }
    \expect($approvals)->toHaveCount(1);
    \expect($approvals[0])->toMatchArray([
        'type' => 'tool-approval-request', 'toolCallId' => 'notification-without-reason',
        'approvalId' => 'notification-without-reason',
    ])->not->toHaveKey('reason');
    Notification::assertNothingSent();
});

\test('direct streaming exposes safe errors for provider exceptions and SDK error events', function (string $failure): void {
    $gateway = new SdkLifecycleTextGateway(['Answer']);
    $gateway->failure = $failure;
    Ai::textProvider()->useTextGateway($gateway->preventStrayPrompts());
    $response = $this->be(UserFactory::new()->createOne(), 'users')
        ->postJson('/agent/chat', ['message' => 'Question'])->assertOk();
    \expect($response->streamedContent())
        ->toContain('ai.errors.provider_failed')
        ->not->toContain('secret-provider-credential');
})->with(['exception', 'event', 'tool_event']);

\test('pre-stream setup errors return a safe response and release the conversation lease', function (): void {
    Ai::textProvider()->useTextGateway((new SdkLifecycleTextGateway(['Recovered']))->preventStrayPrompts());
    $user = UserFactory::new()->createOne();
    $conversation = $user->conversations()->create(['id' => 'setup-error', 'title' => 'Setup']);
    $this->be($user, 'users');
    Config::set('ai.providers.openrouter.model', ['secret-provider-credential']);
    $response = $this->postJson('/agent/chat', ['message' => 'Question', 'conversation_id' => $conversation->getKey()]);
    $response->assertStatus(502)->assertJsonPath('message', 'ai.errors.provider_failed');
    \expect($response->getContent())->not->toContain('secret-provider-credential');
    Config::set('ai.providers.openrouter.model', 'test-model');
    $recovered = $this->postJson('/agent/chat', ['message' => 'Retry', 'conversation_id' => $conversation->getKey()])->assertOk();
    \expect($recovered->streamedContent())->toContain('Recovered');
});

\test('deleting a participant cleans more than one batch without touching another participants history', function (): void {
    $owner = UserFactory::new()->createOne();
    $other = UserFactory::new()->createOne();
    foreach ([$owner, $other] as $participant) {
        $count = $participant->is($owner) ? 105 : 1;
        for ($index = 0; $index < $count; ++$index) {
            $id = $participant->getKey() . '-' . \sprintf('%03d', $index);
            $conversation = $participant->conversations()->create(['id' => $id, 'title' => 'History']);
            $conversation->messages()->create([
                'id' => $id, 'participant_type' => $participant->getMorphClass(),
                'participant_id' => $participant->getKey(), 'agent' => ChatAgent::class,
                'role' => 'user', 'content' => 'Retained history',
                'attachments' => [], 'tool_calls' => [], 'tool_results' => [], 'usage' => [], 'meta' => [],
            ]);
        }
    }
    $owner->delete();
    $this->assertDatabaseMissing('users', ['id' => $owner->getKey()]);
    $this->assertDatabaseHas('users', ['id' => $other->getKey()]);
    $this->assertDatabaseCount('agent_conversations', 1);
    $this->assertDatabaseCount('agent_conversation_messages', 1);
    \expect($owner->conversations()->count())->toBe(0);
    \expect($other->conversations()->count())->toBe(1);
});
