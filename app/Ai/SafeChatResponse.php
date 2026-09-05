<?php

declare(strict_types=1);

namespace App\Ai;

use Generator;
use Illuminate\Contracts\Support\Responsable;
use IteratorAggregate;
use Laravel\Ai\Models\Conversation;
use Laravel\Ai\Responses\Concerns\CanStreamUsingVercelProtocol;
use Laravel\Ai\Responses\StreamableAgentResponse;
use Laravel\Ai\Streaming\Events\Error;
use Laravel\Ai\Streaming\Events\StreamEvent;
use Laravel\Ai\Streaming\Events\ToolResult;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;
use Thinkycz\LaravelCore\Support\Typer;
use Traversable;

/**
 * Sanitize native SDK events without retaining a second event collection.
 *
 * @implements IteratorAggregate<int, StreamEvent>
 */
class SafeChatResponse implements IteratorAggregate, Responsable
{
    use CanStreamUsingVercelProtocol {
        toVercelProtocolPart as private sdkProtocolPart;
    }

    /**
     * Protocol fallback message identifier.
     */
    protected readonly string $invocationId;

    /**
     * Wrap the SDK response while leaving its persistence callbacks intact.
     */
    public function __construct(
        private readonly StreamableAgentResponse $source,
        private readonly string $conversationId,
        protected readonly string|null $vercelProtocolMessageId,
    ) {
        $this->invocationId = $source->invocationId;
    }

    /**
     * Bound direct streaming and translate errors before they reach clients or SDK storage.
     *
     * @return Traversable<int, StreamEvent>
     */
    public function getIterator(): Traversable
    {
        $deadline = \microtime(true) + 90;
        $lastCheck = 0.0;
        foreach ($this->source as $event) {
            $event = Typer::assertInstance($event, StreamEvent::class);
            if ($deadline < \microtime(true) || \connection_aborted() !== 0) {
                throw new RuntimeException('Chat stream stopped.');
            }
            if (\microtime(true) - $lastCheck >= 0.1) {
                if (!Conversation::query()->whereKey($this->conversationId)->exists()) {
                    throw new RuntimeException('Chat conversation was deleted.');
                }
                $lastCheck = \microtime(true);
            }
            if ($event instanceof Error) {
                $event->message = 'ai.errors.provider_failed';
                $event->metadata = null;
            } elseif ($event instanceof ToolResult && !$event->successful && !$event->denied) {
                $event->error = 'ai.errors.provider_failed';
                $event->toolResult->result = 'ai.errors.provider_failed';
            }
            yield $event;
        }
    }

    /**
     * @inheritDoc
     */
    public function toResponse(mixed $request): Response
    {
        return $this->toVercelProtocolResponse();
    }

    /**
     * AI SDK 7 accepts an absent approval reason, but not the Laravel SDK's null.
     *
     * @param array<string, mixed> $data
     *
     * @return Generator<int, string>
     */
    protected function toVercelProtocolPart(object $state, array $data): Generator
    {
        if (($data['type'] ?? null) === 'tool-approval-request' && ($data['reason'] ?? null) === null) {
            unset($data['reason']);
        }

        foreach ($this->sdkProtocolPart($state, $data) as $part) {
            yield Typer::assertString($part);
        }
    }
}
