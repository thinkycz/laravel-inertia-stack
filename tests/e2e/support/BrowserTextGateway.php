<?php

declare(strict_types=1);

namespace Tests\E2e\Support;

use Generator;
use Laravel\Ai\Contracts\Providers\TextProvider;
use Laravel\Ai\Gateway\FakeTextGateway;
use Laravel\Ai\Gateway\StepContext;
use Laravel\Ai\Gateway\TextGenerationOptions;
use Laravel\Ai\Messages\UserMessage;
use Laravel\Ai\Streaming\Events\TextDelta;

class BrowserTextGateway extends FakeTextGateway
{
    /**
     * Emit native SDK events with deterministic pacing for disconnect tests.
     *
     * @inheritDoc
     */
    public function generateStreamStep(string $invocationId, TextProvider $provider, string $model, string|null $instructions, array $messages, array $tools, array|null $schema, TextGenerationOptions|null $options, int|null $timeout, StepContext $stepContext): Generator
    {
        $slow = false;
        foreach ($messages as $message) {
            if ($message instanceof UserMessage) {
                $slow = \str_starts_with($message->content, '[e2e:slow]');
            }
        }
        $stream = parent::generateStreamStep($invocationId, $provider, $model, $instructions, $messages, $tools, $schema, $options, $timeout, $stepContext);
        foreach ($stream as $event) {
            if ($slow && $event instanceof TextDelta) {
                \usleep(500_000);
            }
            yield $event;
        }

        return $stream->getReturn();
    }
}
