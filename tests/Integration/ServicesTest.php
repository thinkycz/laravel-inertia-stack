<?php

declare(strict_types=1);

use App\Ai\Agents\ChatAgent;
use App\Ai\ConversationRepository;
use App\Models\User;
use Database\Factories\UserFactory;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Laravel\Ai\Ai;
use Laravel\Ai\Gateway\FakeTextGateway;
use Laravel\Ai\Models\Conversation;
use Laravel\Ai\Models\ConversationMessage;
use Laravel\Ai\Responses\Data\ToolCall;
use Symfony\Component\Process\Process;
use Tests\TestCase;
use Thinkycz\LaravelCore\Support\Resolver;

\uses(TestCase::class);

\beforeEach(function (): void {
    if (DB::connection()->getDatabaseName() !== \getenv('STARTER_TEST_DATABASE')) {
        throw new RuntimeException('Refusing to migrate a database not owned by this test run.');
    }
    $this->artisan('migrate:fresh', ['--force' => true])->assertSuccessful();
    Queue::connection('redis')->clear('default');
    Http::preventStrayRequests();
    Notification::fake();
});

\test('mysql stores native approval state and cascades conversation ownership', function (): void {
    Ai::textProvider()->useTextGateway(new FakeTextGateway([new ToolCall('native-approval', 'SendTestNotificationTool', ['subject' => 'Subject', 'message' => 'Message'])]));
    $user = UserFactory::new()->createOne();
    $response = $this->be($user, 'users')->postJson('/agent/chat', ['message' => 'Please notify me']);
    $response->assertOk();
    \expect($response->streamedContent())->toContain('tool-approval-request');
    $conversation = $user->conversations()->firstOrFail();
    \expect(Resolver::resolve(ConversationRepository::class)->latestPendingMessageId($conversation))->not->toBeNull();
    $this->assertDatabaseCount('agent_conversation_messages', 2);
    $message = ConversationMessage::query()->firstOrFail();
    \expect(fn() => DB::table('agent_conversation_messages')->insert([...$message->getAttributes(), 'id' => (string) Str::uuid7(), 'conversation_id' => (string) Str::uuid7()]))->toThrow(QueryException::class);
    $user->delete();
    $this->assertDatabaseCount('agent_conversations', 0);
    $this->assertDatabaseCount('agent_conversation_messages', 0);
});

\test('concurrent native approvals execute once and enqueue one notification on redis', function (): void {
    $user = UserFactory::new()->createOne();
    $conversation = \integrationPendingConversation($user);
    $payload = \json_encode(['conversation_id' => $conversation->getKey(), 'decisions' => ['approval-call' => ['action' => 'approve']]], \JSON_THROW_ON_ERROR);
    $processes = [];
    foreach ([1, 2] as $attempt) {
        $process = new Process([\PHP_BINARY, __DIR__ . '/worker.php', 'chat', (string) $user->getKey(), $payload]);
        $process->start();
        $processes[] = $process;
    }
    $codes = \array_map(fn(Process $process) => $process->wait(), $processes);
    \sort($codes);
    \expect($codes)->toBe([0, 3]);
    \expect(Queue::connection('redis')->size())->toBe(1);
    \expect(Resolver::resolve(ConversationRepository::class)->latestPendingMessageId($conversation->refresh()))->toBeNull();
    (new Process([\PHP_BINARY, __DIR__ . '/worker.php', 'work']))->setTimeout(30)->mustRun();
    \expect(Queue::connection('redis')->size())->toBe(0);
    $this->assertDatabaseCount('failed_jobs', 0);
    $duplicate = new Process([\PHP_BINARY, __DIR__ . '/worker.php', 'chat', (string) $user->getKey(), $payload]);
    \expect($duplicate->run())->toBe(3);
    \expect(Queue::connection('redis')->size())->toBe(0);
});

\test('edited and rejected approvals preserve native tool and continuation messages', function (): void {
    $user = UserFactory::new()->createOne();
    foreach (['approve', 'reject'] as $action) {
        $conversation = \integrationPendingConversation($user);
        $decision = $action === 'approve' ? ['action' => 'approve', 'arguments' => ['subject' => 'Edited subject', 'message' => 'Edited body']] : ['action' => 'reject', 'result' => 'Do not send'];
        $payload = \json_encode(['conversation_id' => $conversation->getKey(), 'decisions' => ['approval-call' => $decision]], \JSON_THROW_ON_ERROR);
        $process = new Process([\PHP_BINARY, __DIR__ . '/worker.php', 'chat', (string) $user->getKey(), $payload]);
        \expect($process->run())->toBe(0, $process->getErrorOutput());
        \expect(Resolver::resolve(ConversationRepository::class)->latestPendingMessageId($conversation->refresh()))->toBeNull();
        \expect($conversation->messages()->count())->toBe(2);
        \expect($conversation->messages()->where('role', 'user')->count())->toBe(0);
        $results = $conversation->messages()->firstOrFail()->getAttribute('tool_results');
        \expect($results)->toBeArray()->not->toBeEmpty();
        \expect($results[0]['arguments']['subject'])->toBe($action === 'approve' ? 'Edited subject' : 'Original subject');
        \expect($results[0]['denied'] ?? false)->toBe($action === 'reject');
    }
    \expect(Queue::connection('redis')->size())->toBe(1);
});

\test('mysql deletion concurrent with stream persistence leaves no orphan messages', function (): void {
    $user = UserFactory::new()->createOne();
    $conversation = $user->conversations()->create(['id' => (string) Str::uuid7(), 'title' => 'Delete race']);
    $chat = new Process([\PHP_BINARY, __DIR__ . '/worker.php', 'chat', (string) $user->getKey(), \json_encode(['conversation_id' => $conversation->getKey(), 'message' => 'Concurrent deletion'], \JSON_THROW_ON_ERROR)]);
    $delete = new Process([\PHP_BINARY, __DIR__ . '/worker.php', 'delete', (string) $user->getKey(), (string) $conversation->getKey()]);
    $chat->start();
    $observedActiveStream = false;
    for ($attempt = 0; $attempt < 100; ++$attempt) {
        $probe = Cache::lock('agent-chat:' . $conversation->getKey(), 180);
        if (!$probe->get()) { $observedActiveStream = true;
            break; }
        $probe->release();
        \usleep(10_000);
    }
    \expect($observedActiveStream)->toBeTrue();
    $delete->start();
    \expect($chat->wait())->toBeIn([0, 5]);
    \expect($delete->wait())->toBe(0, $delete->getErrorOutput());
    $this->assertDatabaseCount('agent_conversations', 0);
    $this->assertDatabaseCount('agent_conversation_messages', 0);
});

\test('redis delivery quota remains atomic across concurrent processes', function (): void {
    $first = new Process([\PHP_BINARY, __DIR__ . '/worker.php', 'delivery', 'parallel@example.com']);
    $second = new Process([\PHP_BINARY, __DIR__ . '/worker.php', 'delivery', 'parallel@example.com']);
    $first->start();
    $second->start();
    $codes = [$first->wait(), $second->wait()];
    \sort($codes);
    \expect($codes)->toBe([0, 4]);
});

/**
 * Create SDK-shaped pending approval state without provider calls.
 */
function integrationPendingConversation(User $user): Conversation
{
    $conversation = $user->conversations()->create(['id' => (string) Str::uuid7(), 'title' => 'Pending notification']);
    $conversation->messages()->create([
        'id' => (string) Str::uuid7(), 'participant_type' => $user->getMorphClass(), 'participant_id' => $user->getKey(),
        'agent' => ChatAgent::class, 'role' => 'assistant', 'content' => '', 'attachments' => [],
        'tool_calls' => [['id' => 'approval-call', 'name' => 'SendTestNotificationTool', 'arguments' => ['subject' => 'Original subject', 'message' => 'Original body']]],
        'tool_results' => [], 'usage' => [], 'meta' => [], 'approval_state' => ['pending' => ['approval-call' => null]],
    ]);

    return $conversation;
}
