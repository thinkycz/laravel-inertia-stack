<?php

declare(strict_types=1);

use App\Ai\Agents\ChatAgent;
use App\Models\User;
use Database\Factories\UserFactory;
use Illuminate\Support\Facades\Config;
use Laravel\Ai\Models\Conversation;
use Laravel\Ai\Models\ConversationMessage;
use Laravel\Ai\Prompts\AgentPrompt;
use Laravel\Ai\Responses\Data\ToolCall;
use Thinkycz\LaravelCore\Support\Typer;

\beforeEach(function (): void {
    Config::set('ai.enabled', true);
    Config::set('ai.providers.openrouter.key', 'test-only');
    $this->withSession(['_token' => 'agent-chat-test-token'])
        ->withHeader('X-CSRF-TOKEN', 'agent-chat-test-token');
});

\test('guest cannot stream an agent chat', function (): void {
    $this->postJson('/agent/chat', ['message' => 'Hello'])->assertStatus(401);
});

\test('message and decisions are mutually exclusive', function (): void {
    $user = Typer::assertInstance(UserFactory::new()->createOne(), User::class);

    $this->be($user, 'users')->postJson('/agent/chat', [
        'message' => 'Hello',
        'decisions' => ['call-1' => ['action' => 'approve']],
    ])->assertUnprocessable()->assertJsonValidationErrors(['message', 'decisions']);
});

\test('authenticated user can stream a new remembered conversation', function (): void {
    ChatAgent::fake(['Hello from the agent']);
    $user = Typer::assertInstance(UserFactory::new()->createOne(), User::class);

    $response = $this->be($user, 'users')->postJson('/agent/chat', [
        'message' => 'Hello',
        'conversation_id' => null,
    ]);

    $response->assertOk()->assertHeader('x-vercel-ai-ui-message-stream', 'v1');
    $conversationId = Typer::assertString($response->headers->get('x-conversation-id'));

    \expect($response->streamedContent())
        ->toContain('"type":"text-delta"')
        ->toContain('"delta":"Hello"');
    \expect($user->conversations()->whereKey($conversationId)->exists())->toBeTrue();
    \expect(ConversationMessage::query()->where('conversation_id', $conversationId)->where('role', 'user')->exists())->toBeTrue();
});

\test('user cannot continue another participants conversation', function (): void {
    ChatAgent::fake(['Should not run']);
    $owner = Typer::assertInstance(UserFactory::new()->createOne(), User::class);
    $otherUser = Typer::assertInstance(UserFactory::new()->createOne(), User::class);
    $conversation = $owner->conversations()->create([
        'id' => '019c-test-private-conversation',
        'title' => 'Private',
    ]);

    $this->be($otherUser, 'users')->postJson('/agent/chat', [
        'message' => 'Hello',
        'conversation_id' => $conversation->getKey(),
    ])->assertNotFound();

    ChatAgent::assertNeverPrompted();
});

\test('approvable tool is emitted and persisted as pending', function (): void {
    ChatAgent::fake([
        new ToolCall('notification-call', 'SendTestNotificationTool', [
            'subject' => 'Original subject',
            'message' => 'Original message',
        ]),
    ]);
    $user = Typer::assertInstance(UserFactory::new()->createOne(), User::class);

    $response = $this->be($user, 'users')->postJson('/agent/chat', [
        'message' => 'Send me a notification',
    ]);

    $conversationId = Typer::assertString($response->headers->get('x-conversation-id'));
    \expect($response->streamedContent())
        ->toContain('"type":"tool-approval-request"')
        ->toContain('"toolCallId":"notification-call"');
    \expect(ConversationMessage::query()
        ->where('conversation_id', $conversationId)
        ->where('status', 'paused')
        ->exists())->toBeTrue();
});

\test('approval continuation requires a conversation and complete decisions', function (): void {
    $user = Typer::assertInstance(UserFactory::new()->createOne(), User::class);

    $this->be($user, 'users')->postJson('/agent/chat', [
        'decisions' => [],
    ])->assertUnprocessable()->assertJsonValidationErrors(['decisions']);
});

\test('edited approval arguments are converted to an official edit decision', function (): void {
    ChatAgent::fake(['Continued']);
    $user = Typer::assertInstance(UserFactory::new()->createOne(), User::class);
    $conversation = \createPendingNotificationConversation($user, 'edited-call');

    $response = $this->be($user, 'users')->postJson('/agent/chat', [
        'conversation_id' => $conversation->getKey(),
        'decisions' => [
            'edited-call' => [
                'action' => 'approve',
                'arguments' => [
                    'subject' => 'Edited subject',
                    'message' => 'Edited message',
                ],
            ],
        ],
    ]);

    $response->assertOk();
    $response->streamedContent();

    ChatAgent::assertPrompted(function (AgentPrompt $prompt): bool {
        $decision = $prompt->approvalDecisions?->get('edited-call');

        return $decision?->isEdited() === true &&
            $decision->arguments === [
                'subject' => 'Edited subject',
                'message' => 'Edited message',
            ];
    });
});

\test('rejection result is converted to an official reject decision', function (): void {
    ChatAgent::fake(['Continued']);
    $user = Typer::assertInstance(UserFactory::new()->createOne(), User::class);
    $conversation = \createPendingNotificationConversation($user, 'rejected-call');

    $response = $this->be($user, 'users')->postJson('/agent/chat', [
        'conversation_id' => $conversation->getKey(),
        'decisions' => [
            'rejected-call' => [
                'action' => 'reject',
                'result' => 'Please shorten the message.',
            ],
        ],
    ]);

    $response->assertOk();
    $response->streamedContent();

    ChatAgent::assertPrompted(function (AgentPrompt $prompt): bool {
        $decision = $prompt->approvalDecisions?->get('rejected-call');

        return $decision?->isRejected() === true &&
            $decision->result === 'Please shorten the message.';
    });
});

\test('edited approval arguments must contain a valid subject and message', function (): void {
    $user = Typer::assertInstance(UserFactory::new()->createOne(), User::class);

    $this->be($user, 'users')->postJson('/agent/chat', [
        'conversation_id' => 'conversation-id',
        'decisions' => [
            'call-id' => [
                'action' => 'approve',
                'arguments' => ['subject' => '', 'message' => 'Message'],
            ],
        ],
    ])->assertUnprocessable()->assertJsonValidationErrors('decisions.call-id.arguments.subject');

    ChatAgent::assertNeverPrompted();
});

\test('conversation relationship is polymorphic', function (): void {
    $user = Typer::assertInstance(UserFactory::new()->createOne(), User::class);
    $conversation = $user->conversations()->create([
        'id' => '019c-test-polymorphic-conversation',
        'title' => 'Polymorphic',
    ]);

    \expect($conversation)->toBeInstanceOf(Conversation::class)
        ->and($conversation->getAttribute('participant_type'))->toBe($user->getMorphClass())
        ->and($conversation->getAttribute('participant_id'))->toBe($user->getKey());
});

/**
 * Create an official conversation paused on a notification approval.
 */
function createPendingNotificationConversation(User $user, string $toolCallId): Conversation
{
    $conversation = $user->conversations()->create([
        'id' => '019c-' . $toolCallId,
        'title' => 'Pending notification',
    ]);

    $conversation->messages()->create([
        'id' => '019c-message-' . $toolCallId,
        'participant_type' => $user->getMorphClass(),
        'participant_id' => $user->getKey(),
        'agent' => ChatAgent::class,
        'role' => 'assistant',
        'content' => '',
        'attachments' => [],
        'steps' => [['content' => '', 'tool_calls' => [[
            'id' => $toolCallId,
            'name' => 'SendTestNotificationTool',
            'approval_reason' => null,
            'arguments' => ['subject' => 'Subject', 'message' => 'Message'],
        ]], 'reasoning' => '', 'replay_blocks' => [], 'provider_tool_calls' => []]],
        'status' => 'paused',
        'usage' => [],
        'meta' => [],
    ]);

    return $conversation;
}

\test('disabled AI rejects chat without calling the provider', function (): void {
    Config::set('ai.enabled', false);
    $this->be(UserFactory::new()->createOne(), 'users')->postJson('/agent/chat', ['message' => 'Hello'])->assertNotFound();
    ChatAgent::assertNeverPrompted();
});

\test('missing provider configuration returns an unavailable response', function (): void {
    Config::set('ai.providers.openrouter.key', null);
    $this->be(UserFactory::new()->createOne(), 'users')->postJson('/agent/chat', ['message' => 'Hello'])->assertStatus(503);
    ChatAgent::assertNeverPrompted();
});

\test('provider failures produce a masked terminal stream error', function (): void {
    ChatAgent::fake(static fn(): never => throw new RuntimeException('private-provider-details'));
    $response = $this->be(UserFactory::new()->createOne(), 'users')->postJson('/agent/chat', ['message' => 'Hello']);
    $response->assertOk();
    \expect($response->streamedContent())->toContain('"type":"error"')->not->toContain('private-provider-details');
});

\test('replaying a completed approval cannot invoke the agent again', function (): void {
    ChatAgent::fake(['Completed']);
    $user = UserFactory::new()->createOne();
    $conversation = \createPendingNotificationConversation($user, 'once-only');
    $payload = ['conversation_id' => $conversation->getKey(), 'decisions' => ['once-only' => ['action' => 'approve']]];
    $first = $this->be($user, 'users')->postJson('/agent/chat', $payload);
    $first->assertOk();
    $first->streamedContent();
    ChatAgent::assertPromptedTimes(1);
    $this->postJson('/agent/chat', $payload)->assertUnprocessable()->assertJsonValidationErrors('decisions');
    ChatAgent::assertPromptedTimes(1);
});
