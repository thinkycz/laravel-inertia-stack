<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Ai\AgentConversationContext;
use App\Ai\AgentRunService;
use App\Ai\Agents\ChatAgent;
use App\Models\AgentRun;
use App\Models\AgentRunEvent;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Ai\Models\Conversation;
use Laravel\Ai\Models\ConversationMessage;
use Laravel\Ai\Streaming\Events\StreamEvent;
use Laravel\Ai\Streaming\Events\TextDelta;
use Laravel\Ai\Streaming\Events\ToolCall;
use Laravel\Ai\Streaming\Events\ToolResult;
use RuntimeException;
use Thinkycz\LaravelCore\Support\Resolver;
use Throwable;

class RunChatAgentJob implements ShouldQueue
{
    use Queueable;

    /**
     * Create the job.
     */
    public function __construct(private readonly string $runId) {}

    /**
     * Execute the job.
     */
    public function handle(
        AgentRunService $runs,
        AgentConversationContext $context,
    ): void {
        $run = $this->run();
        if (!$run instanceof AgentRun || $run->getStatus() === AgentRun::STATUS_CANCELLED) {
            return;
        }

        $user = $this->user($run);
        $this->authenticateAs($user);

        DB::transaction(function () use ($runs, $run): void {
            $run->forceFill([
                'status' => AgentRun::STATUS_RUNNING,
                'started_at' => \now(),
            ])->save();

            $runs->recordEvent($run, AgentRunEvent::TYPE_RUN_STARTED, [
                'status' => AgentRun::STATUS_RUNNING,
            ]);
        });

        $context->setConversationId($run->getConversationId());

        $events = new Collection();
        $assistantContent = '';

        try {
            // Use the boilerplate's standard continue() flow (the
            // conversation already has the user message persisted by
            // ConversationController before the run was started).
            $agent = ChatAgent::make()->continue($run->getConversationId(), $user);

            foreach ($agent->stream($run->getPrompt()) as $event) {
                if (!$event instanceof StreamEvent) {
                    continue;
                }

                $run = $this->freshRun($run);
                if ($run->getStatus() === AgentRun::STATUS_CANCELLED) {
                    $this->cancel($runs, $run);

                    return;
                }

                $events->push($event);

                if ($event instanceof TextDelta) {
                    $assistantContent .= $event->delta;
                    $run->forceFill(['assistant_content' => $assistantContent])->save();
                    $runs->recordEvent($run, AgentRunEvent::TYPE_TEXT_DELTA, [
                        'delta' => $event->delta,
                    ]);
                } elseif ($event instanceof ToolCall) {
                    $runs->recordEvent($run, AgentRunEvent::TYPE_TOOL_ACTIVITY, [
                        'stream_type' => 'tool_call',
                        'tool_name' => $event->toolCall->name,
                    ]);
                } elseif ($event instanceof ToolResult) {
                    $runs->recordEvent($run, AgentRunEvent::TYPE_TOOL_ACTIVITY, [
                        'stream_type' => 'tool_result',
                        'tool_name' => $event->toolResult->name,
                    ]);
                }
            }

            $run = $this->freshRun($run);
            if ($run->getStatus() === AgentRun::STATUS_CANCELLED) {
                $this->cancel($runs, $run);

                return;
            }

            $assistantMessageId = $this->persistAssistantMessage($run, $events, $assistantContent, $user);

            DB::transaction(function () use ($runs, $run, $assistantMessageId, $assistantContent): void {
                $run->forceFill([
                    'status' => AgentRun::STATUS_COMPLETED,
                    'assistant_message_id' => $assistantMessageId,
                    'assistant_content' => $assistantContent,
                    'finished_at' => \now(),
                ])->save();

                $runs->recordEvent($run, AgentRunEvent::TYPE_RUN_COMPLETED, [
                    'status' => AgentRun::STATUS_COMPLETED,
                    'assistant_message_id' => $assistantMessageId,
                ]);
            });
        } catch (Throwable $throwable) {
            $run = $this->freshRun($run);

            if ($run->getStatus() === AgentRun::STATUS_CANCELLED) {
                $this->cancel($runs, $run);

                return;
            }

            DB::transaction(function () use ($runs, $run, $assistantContent, $throwable): void {
                $run->forceFill([
                    'status' => AgentRun::STATUS_FAILED,
                    'assistant_content' => $assistantContent,
                    'error' => $throwable->getMessage(),
                    'finished_at' => \now(),
                ])->save();

                $runs->recordEvent($run, AgentRunEvent::TYPE_RUN_FAILED, [
                    'status' => AgentRun::STATUS_FAILED,
                    'error' => $throwable->getMessage(),
                ]);
            });
        }
    }

    /**
     * Load the run.
     */
    private function run(): AgentRun|null
    {
        $run = AgentRun::query()->where('id', $this->runId)->first();

        return $run instanceof AgentRun ? $run : null;
    }

    /**
     * Reload the run.
     */
    private function freshRun(AgentRun $run): AgentRun
    {
        $fresh = AgentRun::query()->where('id', $run->getId())->first();

        if (!$fresh instanceof AgentRun) {
            throw new RuntimeException('Agent run disappeared while processing.');
        }

        return $fresh;
    }

    /**
     * Load the run owner.
     */
    private function user(AgentRun $run): User
    {
        $user = User::query()->where('id', $run->getUserId())->first();

        if (!$user instanceof User) {
            throw new RuntimeException('Agent run owner does not exist.');
        }

        return $user;
    }

    /**
     * Set the queue process auth context for tools and instructions.
     */
    private function authenticateAs(User $user): void
    {
        Resolver::resolveAuthManager()->shouldUse('users');
        Resolver::resolveAuthManager()->guard('users')->setUser($user);
    }

    /**
     * Persist the assistant message generated by the background stream.
     *
     * @param Collection<int, StreamEvent> $events
     */
    private function persistAssistantMessage(AgentRun $run, Collection $events, string $assistantContent, User $user): string
    {
        $messageId = (string) Str::uuid();

        ConversationMessage::query()->create([
            'id' => $messageId,
            'conversation_id' => $run->getConversationId(),
            'user_id' => $user->getKey(),
            'agent' => ChatAgent::class,
            'role' => 'assistant',
            'content' => $assistantContent,
            'attachments' => [],
            'tool_calls' => $events
                ->whereInstanceOf(ToolCall::class)
                ->map(static fn(ToolCall $event): array => $event->toolCall->toArray())
                ->values()
                ->all(),
            'tool_results' => $events
                ->whereInstanceOf(ToolResult::class)
                ->map(static fn(ToolResult $event): array => $event->toolResult->toArray())
                ->values()
                ->all(),
            'usage' => [],
            'meta' => [],
        ]);

        Conversation::query()
            ->where('id', $run->getConversationId())
            ->update(['updated_at' => \now()]);

        return $messageId;
    }

    /**
     * Mark cancellation from inside the running job.
     */
    private function cancel(AgentRunService $runs, AgentRun $run): void
    {
        DB::transaction(function () use ($runs, $run): void {
            $run->forceFill([
                'status' => AgentRun::STATUS_CANCELLED,
                'finished_at' => \now(),
            ])->save();

            $runs->recordEvent($run, AgentRunEvent::TYPE_RUN_CANCELLED, [
                'status' => AgentRun::STATUS_CANCELLED,
            ]);
        });
    }
}
