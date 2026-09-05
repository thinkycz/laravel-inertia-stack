<?php

declare(strict_types=1);

namespace App\Ai\Agents;

use App\Ai\Tools\AskClarifyingQuestionsTool;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Messages\Message;
use Laravel\Ai\Promptable;
use Thinkycz\LaravelCore\Support\Config;

class ChatAgent implements Agent, Conversational, HasTools
{
    use Promptable;

    /**
     * Explicit prior context; application services own all persistence.
     *
     * @param list<Message> $context
     */
    public function __construct(private readonly array $context = []) {}

    /**
     * Completed messages preceding this run.
     *
     * @return list<Message>
     */
    public function messages(): iterable
    {
        return $this->context;
    }

    /**
     * Provider timeout stays below the worker timeout and queue visibility.
     */
    public function timeout(): int
    {
        return 90;
    }

    /**
     * Get the instructions that the agent should follow.
     */
    public function instructions(): string
    {
        return 'You are a helpful AI assistant built into the application boilerplate.';
    }

    /**
     * Get the model for the agent.
     */
    public function model(): string
    {
        return Config::inject()->assertString('ai.providers.openrouter.model');
    }

    /**
     * Get the tools available to the agent.
     *
     * @return Tool[]
     */
    public function tools(): iterable
    {
        yield new AskClarifyingQuestionsTool();
    }
}
