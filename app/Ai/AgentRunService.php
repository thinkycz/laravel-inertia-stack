<?php

declare(strict_types=1);

namespace App\Ai;

use App\Ai\Agents\ChatAgent;
use App\Jobs\RunChatAgentJob;
use App\Models\AgentRun;
use App\Models\AgentRunEvent;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Ai\Models\Conversation;
use Laravel\Ai\Models\ConversationMessage;
use Thinkycz\LaravelCore\Support\Resolver;
use Thinkycz\LaravelCore\Support\Typer;

class AgentRunService
{
    /**
     * Create and dispatch a background agent run.
     *
     * @return array{run: AgentRun, conversation: Conversation}
     */
    public function start(User $user, string $prompt, string|null $conversationId): array
    {
        $conversation = $this->resolveConversation($user, $prompt, $conversationId);

        $activeRun = $this->activeRunForConversation(Typer::assertString($conversation->getKey()), $user);
        if ($activeRun instanceof AgentRun) {
            throw new AgentRunAlreadyActiveException($activeRun);
        }

        /** @var array{run: AgentRun, conversation: Conversation} $result */
        $result = DB::transaction(function () use ($user, $prompt, $conversation): array {
            $userMessageId = $this->persistUserMessage(Typer::assertString($conversation->getKey()), $prompt, $user);

            $run = Typer::assertInstance(AgentRun::query()->create([
                'id' => (string) Str::uuid(),
                'conversation_id' => $conversation->getKey(),
                'user_id' => $user->getKey(),
                'status' => AgentRun::STATUS_QUEUED,
                'prompt' => $prompt,
                'user_message_id' => $userMessageId,
                'assistant_message_id' => null,
                'assistant_content' => '',
                'error' => null,
                'started_at' => null,
                'finished_at' => null,
            ]), AgentRun::class);

            return ['run' => $run, 'conversation' => $conversation];
        });

        Resolver::resolveQueueingDispatcher()->dispatch(new RunChatAgentJob($result['run']->getId()));

        return $result;
    }

    /**
     * Mark a manager-owned run as cancelled.
     */
    public function cancel(User $user, string $runId): AgentRun
    {
        $run = $this->ownedRun($user, $runId);

        if ($run->isActive()) {
            $run->forceFill([
                'status' => AgentRun::STATUS_CANCELLED,
                'finished_at' => \now(),
            ])->save();

            $this->recordEvent($run, AgentRunEvent::TYPE_RUN_CANCELLED, [
                'status' => AgentRun::STATUS_CANCELLED,
            ]);
        }

        return $run;
    }

    /**
     * Get a run owned by the given manager.
     */
    public function ownedRun(User $user, string $runId): AgentRun
    {
        $run = AgentRun::query()
            ->where('id', $runId)
            ->where('user_id', $user->getKey())
            ->first();

        if (!$run instanceof AgentRun) {
            \abort(404);
        }

        return $run;
    }

    /**
     * Get the active run for a conversation.
     */
    public function activeRunForConversation(string $conversationId, User $user): AgentRun|null
    {
        $run = AgentRun::query()
            ->where('conversation_id', $conversationId)
            ->where('user_id', $user->getKey())
            ->whereIn('status', AgentRun::activeStatuses())
            ->orderBy('created_at', 'desc')
            ->first();

        return $run instanceof AgentRun ? $run : null;
    }

    /**
     * Record a persisted SSE event for a run.
     *
     * @param array<string, mixed> $payload
     */
    public function recordEvent(AgentRun $run, string $type, array $payload): AgentRunEvent
    {
        return Typer::assertInstance(AgentRunEvent::query()->create([
            'run_id' => $run->getId(),
            'type' => $type,
            'payload' => $payload,
        ]), AgentRunEvent::class);
    }

    /**
     * Serialize an active run for Inertia props.
     *
     * @return array{id: string, status: string, assistant_content: string, last_event_id: int|null, error: string|null}|null
     */
    public function serializeActiveRun(string|null $conversationId, User $user): array|null
    {
        if ($conversationId === null) {
            return null;
        }

        $run = $this->activeRunForConversation($conversationId, $user);
        if (!$run instanceof AgentRun) {
            return null;
        }

        $lastEventId = AgentRunEvent::query()
            ->where('run_id', $run->getId())
            ->max('id');

        return [
            'id' => Typer::assertString($run->getId()),
            'status' => $run->getStatus(),
            'assistant_content' => $run->getAssistantContent(),
            'last_event_id' => \is_int($lastEventId) ? $lastEventId : null,
            'error' => $run->getError(),
        ];
    }

    /**
     * Resolve an existing owned conversation or create a new one.
     */
    private function resolveConversation(User $user, string $prompt, string|null $conversationId): Conversation
    {
        if ($conversationId !== null) {
            $conversation = Conversation::query()
                ->where('id', $conversationId)
                ->where('user_id', $user->getKey())
                ->first();

            if ($conversation instanceof Conversation) {
                return $conversation;
            }
        }

        return Typer::assertInstance(Conversation::query()->create([
            'id' => (string) Str::uuid(),
            'user_id' => $user->getKey(),
            'title' => Str::limit($prompt, 100, preserveWords: true),
        ]), Conversation::class);
    }

    /**
     * Persist the manager's prompt before the background job starts.
     */
    private function persistUserMessage(string $conversationId, string $prompt, User $user): string
    {
        $messageId = (string) Str::uuid();

        ConversationMessage::query()->create([
            'id' => $messageId,
            'conversation_id' => $conversationId,
            'user_id' => $user->getKey(),
            'agent' => ChatAgent::class,
            'role' => 'user',
            'content' => $prompt,
            'attachments' => [],
            'tool_calls' => [],
            'tool_results' => [],
            'usage' => [],
            'meta' => [],
        ]);

        Conversation::query()
            ->where('id', $conversationId)
            ->update(['updated_at' => \now()]);

        return $messageId;
    }
}
