<?php

declare(strict_types=1);

namespace Tests\E2e\Support;

use App\Ai\Tools\AskClarifyingQuestionsTool;
use Generator;
use Laravel\Ai\Contracts\Providers\TextProvider;
use Laravel\Ai\Gateway\FakeTextGateway;
use Laravel\Ai\Gateway\TextGenerationOptions;
use Laravel\Ai\Messages\UserMessage;
use Laravel\Ai\Responses\Data\ToolCall as ToolCallData;
use Laravel\Ai\Responses\Data\ToolResult as ToolResultData;
use Laravel\Ai\Responses\Data\Usage;
use Laravel\Ai\Streaming\Events\StreamEnd;
use Laravel\Ai\Streaming\Events\StreamStart;
use Laravel\Ai\Streaming\Events\ToolCall;
use Laravel\Ai\Streaming\Events\ToolResult;
use Laravel\Ai\Tools\Request;

class BrowserTextGateway extends FakeTextGateway
{
    /**
     * @inheritDoc
     */
    public function streamText(string $invocationId, TextProvider $provider, string $model, string|null $instructions, array $messages = [], array $tools = [], array|null $schema = null, TextGenerationOptions|null $options = null, int|null $timeout = null): Generator
    {
        $prompt = '';
        foreach ($messages as $message) {
            if ($message instanceof UserMessage) {
                $prompt = $message->content;
            }
        }
        if ($prompt !== '[e2e:clarify]') {
            yield from parent::streamText($invocationId, $provider, $model, $instructions, $messages, $tools, $schema, $options, $timeout);

            return;
        }

        $arguments = ['question' => 'Which language should I use?', 'options' => ['English', 'Czech'], 'recommended_option' => 'English'];
        $call = new ToolCallData('e2e-tool-call', 'AskClarifyingQuestionsTool', $arguments);
        $result = new ToolResultData($call->id, $call->name, $arguments, (new AskClarifyingQuestionsTool())->handle(new Request($arguments)));
        yield (new StreamStart('e2e-start', $provider->name(), $model, \time()))->withInvocationId($invocationId);
        yield (new ToolCall('e2e-call', $call, \time()))->withInvocationId($invocationId);
        yield (new ToolResult('e2e-result', $result, true, null, \time()))->withInvocationId($invocationId);
        yield (new StreamEnd('e2e-end', 'stop', new Usage(), \time()))->withInvocationId($invocationId);
    }
}
