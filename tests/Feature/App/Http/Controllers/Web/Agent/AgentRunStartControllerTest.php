<?php

declare(strict_types=1);

use App\Jobs\RunChatAgentJob;
use App\Models\AgentRun;
use App\Models\User;
use Database\Factories\UserFactory;
use Illuminate\Support\Facades\Queue;
use Laravel\Ai\Models\Conversation;
use Laravel\Ai\Models\ConversationMessage;
use Thinkycz\LaravelCore\Support\Typer;

\test('guest cannot start an agent run', function (): void {
    $response = $this->postJson('/agent/runs', [
        'prompt' => 'Hello',
    ]);

    $response->assertStatus(401);
});

\test('authenticated user can start a background run and a job is dispatched', function (): void {
    Queue::fake();

    $user = Typer::assertInstance(UserFactory::new()->createOne(), User::class);

    $response = $this->be($user, 'users')->postJson('/agent/runs', [
        'prompt' => 'What time is it?',
    ]);

    $response->assertOk();
    $response->assertJsonStructure(['run_id', 'conversation_id', 'status']);
    $response->assertJsonPath('status', AgentRun::STATUS_QUEUED);

    $conversationId = Typer::assertString($response->json('conversation_id'));
    $runId = Typer::assertString($response->json('run_id'));

    static::assertTrue(Conversation::query()
        ->where('id', $conversationId)
        ->where('user_id', $user->getKey())
        ->exists());

    static::assertTrue(ConversationMessage::query()
        ->where('conversation_id', $conversationId)
        ->where('role', 'user')
        ->where('content', 'What time is it?')
        ->exists());

    static::assertTrue(AgentRun::query()
        ->where('id', $runId)
        ->where('conversation_id', $conversationId)
        ->where('user_id', $user->getKey())
        ->exists());

    Queue::assertPushed(RunChatAgentJob::class);
});

\test('second start in the same conversation returns the active run id with 409', function (): void {
    Queue::fake();

    $user = Typer::assertInstance(UserFactory::new()->createOne(), User::class);

    $first = $this->be($user, 'users')->postJson('/agent/runs', [
        'prompt' => 'First prompt',
    ]);
    $first->assertOk();
    $conversationId = Typer::assertString($first->json('conversation_id'));

    // Simulate the run being in flight (the job doesn't run under Queue::fake()).
    AgentRun::query()
        ->where('id', Typer::assertString($first->json('run_id')))
        ->update(['status' => AgentRun::STATUS_RUNNING]);

    $second = $this->be($user, 'users')->postJson('/agent/runs', [
        'prompt' => 'Second prompt',
        'conversation_id' => $conversationId,
    ]);

    $second->assertStatus(409);
    $second->assertJsonPath('run_id', Typer::assertString($first->json('run_id')));
    $second->assertJsonPath('conversation_id', $conversationId);
    $second->assertJsonPath('status', AgentRun::STATUS_RUNNING);
});

\test('successful starts consume the thirty per-user quota while invalid requests do not', function (): void {
    Queue::fake();
    $user = UserFactory::new()->createOne();
    $this->be($user, 'users');
    for ($attempt = 0; $attempt < 32; ++$attempt) {
        $this->postJson('/agent/runs', ['prompt' => ''])->assertUnprocessable();
    }
    for ($attempt = 0; $attempt < 30; ++$attempt) {
        $this->postJson('/agent/runs', ['prompt' => 'Valid prompt'])->assertOk();
    }
    $this->postJson('/agent/runs', ['prompt' => 'Over budget'])->assertStatus(429);
    $this->assertDatabaseCount('agent_runs', 30);
});
