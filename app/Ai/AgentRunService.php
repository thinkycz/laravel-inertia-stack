<?php

declare(strict_types=1);

namespace App\Ai;

use App\Ai\Agents\ChatAgent;
use App\Jobs\RunChatAgentJob;
use App\Models\AgentRun;
use App\Models\AgentRunEvent;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Ai\Models\Conversation;
use Laravel\Ai\Models\ConversationMessage;
use Thinkycz\LaravelCore\Support\Resolver;
use Thinkycz\LaravelCore\Support\Typer;
use Throwable;

class AgentRunService
{
    /**
     * Persist one prompt and placeholder, then dispatch outside the transaction.
     *
     * @return array{run: AgentRun, conversation: Conversation}
     */
    public function start(User $user, string $prompt, string|null $conversationId, string|null $clarificationMessageId = null): array
    {
        $result = DB::transaction(function () use ($user, $prompt, $conversationId, $clarificationMessageId): array {
            if ($conversationId === null) {
                $conversation = Conversation::query()->create([
                    'id' => (string) Str::uuid7(), 'user_id' => $user->getKey(),
                    'title' => Str::limit($prompt, 100, preserveWords: true),
                ]);
            } else {
                $conversation = Conversation::query()->whereKey($conversationId)
                    ->where('user_id', $user->getKey())->lockForUpdate()->first();
                if (!$conversation instanceof Conversation) {
                    \abort(404);
                }
            }

            $id = Typer::assertString($conversation->getKey());
            $active = $this->activeRunForConversation($id, $user);
            if ($active !== null) {
                throw new AgentRunAlreadyActiveException($active);
            }

            if ($clarificationMessageId !== null) {
                $message = ConversationMessage::query()->whereKey($clarificationMessageId)
                    ->where('conversation_id', $id)->where('role', 'assistant')->lockForUpdate()->first();
                if (!$message instanceof ConversationMessage) {
                    \abort(404);
                }
                $meta = Typer::assertArray($message->getAttribute('meta'));
                if (!isset($meta['clarification']) || $message->getAttribute('clarification_answer') !== null) {
                    \abort(409);
                }
                $message->update(['clarification_answer' => $prompt]);
            }

            $run = AgentRun::query()->create([
                'id' => (string) Str::uuid7(), 'conversation_id' => $id,
                'active_conversation_id' => $id, 'user_id' => $user->getKey(),
                'status' => AgentRun::STATUS_QUEUED, 'prompt' => $prompt,
                'user_message_id' => (string) Str::uuid7(),
                'assistant_message_id' => (string) Str::uuid7(),
                'assistant_content' => '', 'last_event_id' => 0,
            ]);

            foreach (['user' => $run->getUserMessageId(), 'assistant' => $run->getAssistantMessageId()] as $role => $messageId) {
                ConversationMessage::query()->create([
                    'id' => $messageId, 'run_id' => $run->getId(), 'conversation_id' => $id,
                    'user_id' => $user->getKey(), 'agent' => ChatAgent::class,
                    'role' => $role, 'content' => $role === 'user' ? $prompt : '',
                    'status' => AgentRun::STATUS_QUEUED, 'attachments' => [],
                    'tool_calls' => [], 'tool_results' => [], 'usage' => [], 'meta' => [],
                ]);
            }
            $conversation->touch();

            return ['run' => $run, 'conversation' => $conversation];
        });

        try {
            Resolver::resolveQueueingDispatcher()->dispatch(new RunChatAgentJob($result['run']->getId()));
        } catch (Throwable $exception) {
            \report($exception);
            $this->finish($result['run']->getId(), AgentRun::STATUS_FAILED, error: 'ai.errors.dispatch_failed');
        }

        $result['run']->refresh();

        return $result;
    }

    /**
     * Claim exactly once, even when a queue delivers a job more than once.
     */
    public function claim(string $runId): AgentRun|null
    {
        return DB::transaction(function () use ($runId): AgentRun|null {
            $run = $this->lockedRun($runId);
            if (!$run instanceof AgentRun || $run->getStatus() !== AgentRun::STATUS_QUEUED) {
                return null;
            }
            $run->forceFill(['status' => AgentRun::STATUS_RUNNING, 'started_at' => \now()])->save();
            $this->recordEvent($run, AgentRunEvent::TYPE_RUN_STARTED, ['status' => AgentRun::STATUS_RUNNING]);

            return $run;
        });
    }

    /**
     * Flush text and metadata together with their replay event and cursor.
     *
     * @param array<string, mixed> $metadata
     * @param array<string, mixed> $payload
     */
    public function flush(string $runId, string $delta, array $metadata = [], string $type = AgentRunEvent::TYPE_TEXT_DELTA, array $payload = []): bool
    {
        return DB::transaction(function () use ($runId, $delta, $metadata, $type, $payload): bool {
            $run = $this->lockedRun($runId);
            if (!$run instanceof AgentRun || $run->getStatus() !== AgentRun::STATUS_RUNNING) {
                return false;
            }
            $run->forceFill(['assistant_content' => $run->getAssistantContent() . $delta]);
            ConversationMessage::query()->whereKey($run->getAssistantMessageId())
                ->update(['content' => $run->getAssistantContent(), ...$metadata]);
            $this->recordEvent($run, $type, ['delta' => $delta, ...$payload]);

            return true;
        });
    }

    /**
     * Settle only active runs. Terminal state and history are committed together.
     */
    public function finish(string $runId, string $status, string $delta = '', string|null $error = null): AgentRun|null
    {
        Typer::assertTrue(\in_array($status, [AgentRun::STATUS_COMPLETED, AgentRun::STATUS_FAILED, AgentRun::STATUS_CANCELLED], true));

        return DB::transaction(function () use ($runId, $status, $delta, $error): AgentRun|null {
            $run = $this->lockedRun($runId);
            if (!$run instanceof AgentRun || !$run->isActive()) {
                return $run;
            }
            if ($status === AgentRun::STATUS_COMPLETED && $run->getStatus() !== AgentRun::STATUS_RUNNING) {
                return $run;
            }
            $run->forceFill([
                'status' => $status, 'active_conversation_id' => null,
                'assistant_content' => $run->getAssistantContent() . $delta,
                'finished_at' => \now(), 'error' => $error,
            ]);
            ConversationMessage::query()->where('run_id', $runId)->update(['status' => $status]);
            ConversationMessage::query()->whereKey($run->getAssistantMessageId())->update(['content' => $run->getAssistantContent()]);
            $this->recordEvent($run, 'run_' . $status, $this->snapshot($run));
            Conversation::query()->whereKey($run->getConversationId())->update(['updated_at' => \now()]);

            return $run;
        });
    }

    /**
     * Recheck age under the row lock so a newly claimed run cannot be failed as stale queued work.
     */
    public function reconcile(string $runId): void
    {
        DB::transaction(function () use ($runId): void {
            $run = $this->lockedRun($runId);
            if ($run === null) {
                return;
            }
            $stale = AgentRun::query()->whereKey($runId)->where(function (Builder $query): void {
                $query->where(function (Builder $queued): void {
                    $queued->where('status', AgentRun::STATUS_QUEUED)->where('created_at', '<', \now()->subMinutes(5));
                })->orWhere(function (Builder $running): void {
                    $running->where('status', AgentRun::STATUS_RUNNING)->where('started_at', '<', \now()->subSeconds(150));
                });
            })->exists();
            if ($stale) {
                $this->finish($runId, AgentRun::STATUS_FAILED, error: 'ai.errors.reconciliation_failed');
            }
        });
    }

    /**
     * Cancel a run owned by this user.
     */
    public function cancel(User $user, string $runId): AgentRun
    {
        $run = $this->ownedRun($user, $runId);

        return $this->finish($run->getId(), AgentRun::STATUS_CANCELLED) ?? $run;
    }

    /**
     * Resolve an owned run without exposing another user's resource.
     */
    public function ownedRun(User $user, string $runId): AgentRun
    {
        $run = AgentRun::query()->whereKey($runId)->where('user_id', $user->getKey())->first();
        if (!$run instanceof AgentRun) {
            \abort(404);
        }

        return $run;
    }

    /**
     * Find the single active run belonging to the conversation's owner.
     */
    public function activeRunForConversation(string $conversationId, User $user): AgentRun|null
    {
        return AgentRun::query()->where('conversation_id', $conversationId)->where('user_id', $user->getKey())
            ->whereIn('status', AgentRun::activeStatuses())->first();
    }

    /**
     * Append inside the caller's transaction, saving the matching content cursor.
     *
     * @param array<string, mixed> $payload
     */
    public function recordEvent(AgentRun $run, string $type, array $payload): AgentRunEvent
    {
        $event = AgentRunEvent::query()->create(['run_id' => $run->getId(), 'type' => $type, 'payload' => $payload]);
        $run->forceFill(['last_event_id' => $event->getId()])->save();

        return $event;
    }

    /**
     * Serialize one committed row, so content and cursor cannot disagree.
     *
     * @return array{id: string, run_id: string, conversation_id: string, assistant_message_id: string|null, status: string, assistant_content: string, last_event_id: int, error: string|null}
     */
    public function snapshot(AgentRun $run): array
    {
        return [
            'id' => $run->getId(), 'run_id' => $run->getId(), 'conversation_id' => $run->getConversationId(),
            'assistant_message_id' => $run->getAssistantMessageId(), 'status' => $run->getStatus(),
            'assistant_content' => $run->getAssistantContent(), 'last_event_id' => $run->getLastEventId(),
            'error' => $run->getError(),
        ];
    }

    /**
     * Active snapshot for page hydration.
     *
     * @return array<string, mixed>|null
     */
    public function serializeActiveRun(string|null $conversationId, User $user): array|null
    {
        $run = $conversationId === null ? null : $this->activeRunForConversation($conversationId, $user);

        return $run === null ? null : $this->snapshot($run);
    }

    /**
     * Lock parents before children, matching conversation deletion and new starts.
     */
    private function lockedRun(string $runId): AgentRun|null
    {
        $conversationId = AgentRun::query()->whereKey($runId)->value('conversation_id');
        if (!\is_string($conversationId) || Conversation::query()->whereKey($conversationId)->lockForUpdate()->first() === null) {
            return null;
        }

        return AgentRun::query()->whereKey($runId)->lockForUpdate()->first();
    }
}
