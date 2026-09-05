<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Ai\AgentRunService;
use App\Ai\Agents\ChatAgent;
use App\Ai\ConversationRepository;
use App\Models\AgentRun;
use App\Models\AgentRunEvent;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Laravel\Ai\Responses\Data\Usage;
use Laravel\Ai\Streaming\Events\StreamEnd;
use Laravel\Ai\Streaming\Events\StreamStart;
use Laravel\Ai\Streaming\Events\TextDelta;
use Laravel\Ai\Streaming\Events\ToolCall;
use Laravel\Ai\Streaming\Events\ToolResult;
use Thinkycz\LaravelCore\Support\Resolver;
use Throwable;

class RunChatAgentJob implements ShouldQueue
{
    use Queueable;

    /**
     * Maximum worker execution time in seconds.
     */
    public int $timeout = 120;

    /**
     * Provider executions are never retried automatically.
     */
    public int $tries = 1;

    /**
     * Timeouts must trigger failed-job handling.
     */
    public bool $failOnTimeout = true;

    /**
     * Create a run-specific job without process-global authentication.
     */
    public function __construct(private readonly string $runId) {}

    /**
     * Execute claimed work, buffering small text deltas before committing.
     */
    public function handle(AgentRunService $runs): void
    {
        $run = $runs->claim($this->runId);
        if ($run === null) {
            return;
        }

        $buffer = '';
        $lastFlush = \microtime(true);
        $toolCalls = [];
        $toolResults = [];
        $meta = [];
        $usage = new Usage();

        try {
            $agent = new ChatAgent(Resolver::resolve(ConversationRepository::class)->providerMessages($run));
            foreach ($agent->stream($run->getPrompt(), timeout: 90) as $event) {
                if ($event instanceof StreamStart) {
                    $meta['provider'] = $event->provider;
                    $meta['model'] = $event->model;
                } elseif ($event instanceof TextDelta) {
                    $buffer .= $event->delta;
                    if (\mb_strlen($buffer, '8bit') < 4096 && \microtime(true) - $lastFlush < 0.1) {
                        continue;
                    }
                    if (!$runs->flush($this->runId, $buffer, ['meta' => $meta])) {
                        return;
                    }
                    $buffer = '';
                    $lastFlush = \microtime(true);
                } elseif ($event instanceof ToolCall || $event instanceof ToolResult) {
                    $clarification = null;
                    $payload = ['stream_type' => $event instanceof ToolCall ? 'tool_call' : 'tool_result'];
                    if ($event instanceof ToolCall) {
                        $toolCalls[] = $event->toolCall->toArray();
                        $payload['tool_name'] = $event->toolCall->name;
                    } else {
                        $toolResults[] = $event->toolResult->toArray();
                        $payload['tool_name'] = $event->toolResult->name;
                        $clarification = $this->clarification($event->toolResult->result);
                        if ($clarification !== null) {
                            $meta['clarification'] = $payload['clarification'] = $clarification;
                        }
                    }
                    if (!$runs->flush($this->runId, $buffer, [
                        'tool_calls' => $toolCalls, 'tool_results' => $toolResults, 'meta' => $meta,
                    ], AgentRunEvent::TYPE_TOOL_ACTIVITY, $payload)) {
                        return;
                    }
                    $buffer = '';
                    if ($clarification !== null) {
                        $runs->finish($this->runId, AgentRun::STATUS_COMPLETED);

                        return;
                    }
                    $lastFlush = \microtime(true);
                } elseif ($event instanceof StreamEnd) {
                    $usage = $usage->add($event->usage);
                    if (!$runs->flush($this->runId, $buffer, ['usage' => $usage->toArray(), 'meta' => $meta])) {
                        return;
                    }
                    $buffer = '';
                    $lastFlush = \microtime(true);
                }
            }
            $runs->finish($this->runId, AgentRun::STATUS_COMPLETED, $buffer);
        } catch (Throwable $exception) {
            \report($exception);
            $runs->flush($this->runId, $buffer, ['meta' => $meta]);
            $runs->finish($this->runId, AgentRun::STATUS_FAILED, error: 'ai.errors.provider_failed');
        }
    }

    /**
     * Settle worker-level failures, including enforced timeouts.
     */
    public function failed(Throwable|null $exception): void
    {
        Resolver::resolve(AgentRunService::class)->finish($this->runId, AgentRun::STATUS_FAILED, error: 'ai.errors.worker_failed');
    }

    /**
     * Accept only a structurally valid clarification returned by the tool.
     *
     * @return array{question: string, options: list<string>, recommended_option: string|null}|null
     */
    private function clarification(mixed $result): array|null
    {
        if (!\is_string($result)) {
            return null;
        }
        $value = \json_decode($result, true);
        if (!\is_array($value) || !\is_string($value['question'] ?? null) || !\is_array($value['options'] ?? null)) {
            return null;
        }
        $options = [];
        foreach ($value['options'] as $option) {
            if (!\is_string($option) || \trim($option) === '') {
                return null;
            }
            $options[] = $option;
        }
        $recommended = $value['recommended_option'] ?? null;
        if (\count($options) < 2 || \count($options) > 5 || ($recommended !== null && (!\is_string($recommended) || !\in_array($recommended, $options, true)))) {
            return null;
        }

        return ['question' => $value['question'], 'options' => $options, 'recommended_option' => $recommended];
    }
}
