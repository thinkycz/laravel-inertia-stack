<?php

declare(strict_types=1);

use App\Ai\AgentRunService;
use App\Jobs\ReconcileAgentRunsJob;
use App\Models\AgentRun;
use Database\Factories\UserFactory;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Laravel\Ai\Models\Conversation;
use Symfony\Component\Process\Process;
use Tests\TestCase;
use Thinkycz\LaravelCore\Support\Resolver;

\uses(TestCase::class);

\beforeEach(function (): void {
    if (DB::connection()->getDatabaseName() !== \getenv('STARTER_TEST_DATABASE')) {
        throw new RuntimeException('Refusing to migrate a database not owned by this test run.');
    }
    $this->artisan('migrate:fresh', ['--force' => true])->assertSuccessful();
    Http::preventStrayRequests();
    Notification::fake();
});

\test('mysql enforces active uniqueness and cascading ownership', function (): void {
    Queue::fake();
    $user = UserFactory::new()->createOne();
    $run = Resolver::resolve(AgentRunService::class)->start($user, 'Question', null)['run'];
    \expect(fn() => AgentRun::query()->insert([
        ...$run->getAttributes(), 'id' => 'duplicate-run',
    ]))->toThrow(QueryException::class);
    $user->delete();
    $this->assertDatabaseCount('agent_runs', 0);
    $this->assertDatabaseCount('agent_conversations', 0);
    $this->assertDatabaseCount('agent_conversation_messages', 0);
});

\test('mysql concurrent starts allow one active run and terminal transitions settle once', function (): void {
    $user = UserFactory::new()->createOne();
    $conversation = Conversation::query()->create(['id' => (string) Str::uuid7(), 'user_id' => $user->getKey(), 'title' => 'Concurrency']);
    $processes = [];
    foreach ([1, 2] as $attempt) {
        $process = new Process([\PHP_BINARY, __DIR__ . '/worker.php', 'start', (string) $user->getKey(), $conversation->getKey()]);
        $process->start();
        $processes[] = $process;
    }
    $codes = \array_map(fn(Process $process) => $process->wait(), $processes);
    \sort($codes);
    \expect($codes)->toBe([0, 3]);
    $this->assertDatabaseCount('agent_runs', 1);
    $this->assertDatabaseCount('agent_conversation_messages', 2);
    $run = AgentRun::query()->firstOrFail();
    Resolver::resolve(AgentRunService::class)->claim($run->getId());
    $complete = new Process([\PHP_BINARY, __DIR__ . '/worker.php', 'complete', $run->getId()]);
    $cancel = new Process([\PHP_BINARY, __DIR__ . '/worker.php', 'cancel', $run->getId()]);
    $complete->start();
    $cancel->start();
    \expect($complete->wait())->toBe(0);
    \expect($cancel->wait())->toBe(0);
    \expect($run->refresh()->getStatus())->toBeIn([AgentRun::STATUS_COMPLETED, AgentRun::STATUS_CANCELLED]);
    \expect(DB::table('agent_run_events')->whereIn('type', ['run_completed', 'run_cancelled'])->count())->toBe(1);
    \expect($run->getAttribute('active_conversation_id'))->toBeNull();
});

\test('redis worker executes once and recovery settles abandoned work', function (): void {
    $user = UserFactory::new()->createOne();
    $runs = Resolver::resolve(AgentRunService::class);
    $run = $runs->start($user, 'Redis prompt', null)['run'];
    $worker = new Process([\PHP_BINARY, __DIR__ . '/worker.php', 'work']);
    $worker->setTimeout(30)->mustRun();
    \expect($run->refresh()->getStatus())->toBe(AgentRun::STATUS_COMPLETED);
    \expect($run->getAssistantContent())->toBe('Disposable worker answer');
    $this->assertDatabaseCount('agent_conversation_messages', 2);
    $stale = $runs->start($user, 'Abandoned', null)['run'];
    $runs->claim($stale->getId());
    $stale->forceFill(['started_at' => \now()->subSeconds(151)])->save();
    Resolver::resolveQueueingDispatcher()->dispatch(new ReconcileAgentRunsJob());
    (new Process([\PHP_BINARY, __DIR__ . '/worker.php', 'work']))->setTimeout(30)->mustRun();
    \expect($stale->refresh()->getStatus())->toBe(AgentRun::STATUS_FAILED);
});

\test('mysql concurrent deletion and worker writes settle without orphan rows', function (): void {
    Queue::fake();
    $user = UserFactory::new()->createOne();
    $runs = Resolver::resolve(AgentRunService::class);
    for ($attempt = 0; $attempt < 4; ++$attempt) {
        $run = $runs->start($user, 'Delete race', null)['run'];
        $runs->claim($run->getId());
        $delete = new Process([\PHP_BINARY, __DIR__ . '/worker.php', 'delete', $run->getConversationId()]);
        $write = new Process([\PHP_BINARY, __DIR__ . '/worker.php', $attempt % 2 === 0 ? 'flush' : 'complete', $run->getId()]);
        $delete->start();
        $write->start();
        \expect($delete->wait())->toBe(0, $delete->getErrorOutput());
        \expect($write->wait())->toBe(0, $write->getErrorOutput());
    }
    $this->assertDatabaseCount('agent_runs', 0);
    $this->assertDatabaseCount('agent_run_events', 0);
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
