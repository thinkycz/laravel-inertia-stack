<?php

declare(strict_types=1);

namespace Tests\Feature\App\Ai;

use Generator;
use Laravel\Ai\Contracts\Providers\TextProvider;
use Laravel\Ai\Gateway\FakeTextGateway;
use Laravel\Ai\Gateway\StepContext;
use Laravel\Ai\Gateway\TextGenerationOptions;
use Laravel\Ai\Responses\Data\ToolCall;
use Laravel\Ai\Responses\Data\ToolResult;
use Laravel\Ai\Streaming\Events\Error as StreamError;
use Laravel\Ai\Streaming\Events\ToolCall as ToolCallEvent;
use Laravel\Ai\Streaming\Events\ToolResult as ToolResultEvent;
use RuntimeException;

/**
 * Exercise the official SDK loop and remembering with deterministic transport.
 */
class SdkLifecycleTextGateway extends FakeTextGateway
{
    /**
     * Messages that each actual generation step receives.
     *
     * @var list<list<string>>
     */
    public array $contexts = [];

    /**
     * Optional synthetic provider failure shape.
     */
    public string|null $failure = null;

    /**
     * @inheritDoc
     */
    public function generateStreamStep(string $invocationId, TextProvider $provider, string $model, string|null $instructions, array $messages, array $tools, array|null $schema, TextGenerationOptions|null $options, int|null $timeout, StepContext $stepContext): Generator
    {
        $this->contexts[] = \array_map(static fn($message): string => $message->content, $messages);
        if ($this->failure === 'exception') {
            throw new RuntimeException('secret-provider-credential');
        }
        if ($this->failure === 'event') {
            yield (new StreamError('synthetic-error', 'provider_error', 'secret-provider-credential', false, \time()))->withInvocationId($invocationId);
        }

        if ($this->failure === 'tool_event') {
            yield (new ToolCallEvent('synthetic-call', new ToolCall('failed-tool', 'SendTestNotificationTool', []), \time()))->withInvocationId($invocationId);
            yield (new ToolResultEvent('synthetic-result', new ToolResult('failed-tool', 'SendTestNotificationTool', [], 'secret-provider-credential'), false, 'secret-provider-credential', \time()))->withInvocationId($invocationId);
        }

        return yield from parent::generateStreamStep($invocationId, $provider, $model, $instructions, $messages, $tools, $schema, $options, $timeout, $stepContext);
    }
}
