<?php

declare(strict_types=1);

use App\Ai\AgentRunService;
use App\Ai\Agents\ChatAgent;
use App\Ai\ConversationRepository;
use App\Jobs\ReconcileAgentRunsJob;
use App\Jobs\RunChatAgentJob;
use App\Models\AgentRun;
use Database\Factories\UserFactory;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Laravel\Ai\Models\ConversationMessage;
use Thinkycz\LaravelCore\Support\Resolver;

\test('a run owns one message pair and duplicate delivery does not append messages', function (): void {
    Queue::fake();
    ChatAgent::fake(['Answer']);
    $user = UserFactory::new()->createOne();
    $service = Resolver::resolve(AgentRunService::class);
    $result = $service->start($user, 'Question', null);
    $this->assertDatabaseCount('agent_conversation_messages', 2);
    (new RunChatAgentJob($result['run']->getId()))->handle($service);
    (new RunChatAgentJob($result['run']->getId()))->handle($service);
    $this->assertDatabaseCount('agent_conversation_messages', 2);
    \expect($result['run']->refresh()->getStatus())->toBe(AgentRun::STATUS_COMPLETED);
    \expect(ConversationMessage::query()->where('role', 'assistant')->value('content'))->toBe('Answer');
});

\test('unknown conversation cannot silently create a new conversation', function (): void {
    Queue::fake();
    $user = UserFactory::new()->createOne();
    $this->be($user, 'users')->postJson('/agent/runs', ['prompt' => 'Question', 'conversation_id' => 'missing'])->assertNotFound();
    $this->assertDatabaseCount('agent_conversations', 0);
});

\test('two hundred small deltas use fewer than one hundred SQL statements', function (): void {
    Queue::fake();
    ChatAgent::fake([\implode(' ', \array_fill(0, 200, 'delta'))]);
    $service = Resolver::resolve(AgentRunService::class);
    $result = $service->start(UserFactory::new()->createOne(), 'Question', null);
    $queries = 0;
    DB::listen(function () use (&$queries): void { ++$queries; });
    (new RunChatAgentJob($result['run']->getId()))->handle($service);
    \expect($queries)->toBeLessThan(100);
    \expect($result['run']->refresh()->getAssistantContent())->toBe(\implode(' ', \array_fill(0, 200, 'delta')));
});

\test('cancellation is terminal and preserves flushed partial content', function (): void {
    Queue::fake();
    $user = UserFactory::new()->createOne();
    $service = Resolver::resolve(AgentRunService::class);
    $run = $service->start($user, 'Question', null)['run'];
    $service->claim($run->getId());
    $service->flush($run->getId(), 'Partial');
    $service->cancel($user, $run->getId());
    $service->finish($run->getId(), AgentRun::STATUS_COMPLETED, ' late output');
    \expect($run->refresh()->getStatus())->toBe(AgentRun::STATUS_CANCELLED);
    \expect($run->getAssistantContent())->toBe('Partial');
    \expect($service->flush($run->getId(), ' more output'))->toBeFalse();
    \expect($service->start($user, 'Next', $run->getConversationId())['run']->isActive())->toBeTrue();
});

\test('deleting a conversation cascades runs events and messages and worker writes stop', function (): void {
    Queue::fake();
    $service = Resolver::resolve(AgentRunService::class);
    $result = $service->start(UserFactory::new()->createOne(), 'Question', null);
    $service->claim($result['run']->getId());
    $result['conversation']->delete();
    \expect($service->flush($result['run']->getId(), 'Late'))->toBeFalse();
    \expect($service->finish($result['run']->getId(), AgentRun::STATUS_COMPLETED))->toBeNull();
    $this->assertDatabaseCount('agent_runs', 0);
    $this->assertDatabaseCount('agent_run_events', 0);
    $this->assertDatabaseCount('agent_conversation_messages', 0);
});

\test('provider context excludes the current pair and preserves completed prior turns', function (): void {
    Queue::fake();
    ChatAgent::fake(['First answer']);
    $user = UserFactory::new()->createOne();
    $service = Resolver::resolve(AgentRunService::class);
    $first = $service->start($user, 'First question', null)['run'];
    (new RunChatAgentJob($first->getId()))->handle($service);
    $second = $service->start($user, 'Second question', $first->getConversationId())['run'];
    $context = Resolver::resolve(ConversationRepository::class)->providerMessages($second);
    \expect(\array_map(fn($message) => $message->content, $context))->toBe(['First question', 'First answer']);
});

\test('dispatch failures release the active slot and expose only a safe error code', function (): void {
    Bus::shouldReceive('dispatch')->andThrow(new RuntimeException('Secret provider credential'));
    $service = Resolver::resolve(AgentRunService::class);
    $run = $service->start(UserFactory::new()->createOne(), 'Question', null)['run'];
    \expect($run->getStatus())->toBe(AgentRun::STATUS_FAILED);
    \expect($run->getError())->toBe('ai.errors.dispatch_failed');
    \expect($run->getAttribute('active_conversation_id'))->toBeNull();
});

\test('stale queued and running jobs settle while recent work stays active', function (): void {
    Queue::fake();
    $service = Resolver::resolve(AgentRunService::class);
    $user = UserFactory::new()->createOne();
    $queued = $service->start($user, 'Queued', null)['run'];
    $running = $service->start($user, 'Running', null)['run'];
    $recent = $service->start($user, 'Recent', null)['run'];
    $queued->forceFill(['created_at' => \now()->subMinutes(6)])->save();
    $service->claim($running->getId());
    $running->forceFill(['started_at' => \now()->subSeconds(151)])->save();
    (new ReconcileAgentRunsJob())->handle($service);
    \expect($queued->refresh()->getStatus())->toBe(AgentRun::STATUS_FAILED);
    \expect($running->refresh()->getStatus())->toBe(AgentRun::STATUS_FAILED);
    \expect($recent->refresh()->isActive())->toBeTrue();
});
