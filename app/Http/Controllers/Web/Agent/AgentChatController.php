<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Agent;

use App\Ai\Agents\ChatAgent;
use App\Ai\ConversationRepository;
use App\Http\Controllers\Web\Concerns\ThrottlesWebRequests;
use App\Http\Controllers\Web\Concerns\ValidatesWebRequests;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Laravel\Ai\Approvals\Decision;
use Laravel\Ai\Approvals\Decisions;
use Laravel\Ai\Contracts\ConversationStore;
use Laravel\Ai\Models\Conversation;
use Symfony\Component\HttpFoundation\Response;
use Thinkycz\LaravelCore\Http\RequestSignature;
use Thinkycz\LaravelCore\Support\Resolver;
use Thinkycz\LaravelCore\Support\Typer;

class AgentChatController
{
    use ThrottlesWebRequests;
    use ValidatesWebRequests;

    /**
     * Stream a new chat turn or resume official tool approvals.
     */
    public function __invoke(Request $request): Response
    {
        $validated = $this->validateRequest($request, [
            'message' => ['nullable', 'string', 'max:10000', 'required_without:decisions', 'prohibited_with:decisions'],
            'conversation_id' => ['nullable', 'string', 'required_with:decisions'],
            'decisions' => ['nullable', 'array', 'min:1', 'required_without:message', 'prohibited_with:message'],
            'decisions.*' => ['required', 'array:action,arguments,result'],
            'decisions.*.action' => ['required', 'string', 'in:approve,reject'],
            'decisions.*.arguments' => ['nullable', 'array:subject,message'],
            'decisions.*.arguments.subject' => ['required_with:decisions.*.arguments', 'string', 'max:255'],
            'decisions.*.arguments.message' => ['required_with:decisions.*.arguments', 'string', 'max:5000'],
            'decisions.*.result' => ['nullable', 'string', 'max:5000'],
        ]);

        $this->hit($this->limit());

        $user = User::mustAuth();
        $conversations = Resolver::resolve(ConversationRepository::class);
        $conversationId = $validated->parseNullableString('conversation_id');
        $conversation = $conversationId === null ? null : $conversations->findOwned($conversationId, $user);

        if ($conversationId !== null && !$conversation instanceof Conversation) {
            \abort(404);
        }

        $message = $validated->parseNullableString('message');

        if ($conversation === null) {
            $conversationId = Resolver::resolve(ConversationStore::class)->storeConversation(
                Conversation::participantType($user),
                Conversation::participantKey($user),
                Str::limit(Typer::assertString($message), 100, preserveWords: true),
            );
            $conversation = $conversations->findOwned($conversationId, $user);
        }

        $agent = ChatAgent::make()->continue($conversationId, $user);
        $prompt = $message ?? $this->decisions($validated->parseArray('decisions'));
        $response = $agent->stream($prompt)
            ->usingVercelDataProtocol(true, $conversations->latestPendingMessageId(Typer::assertInstance($conversation, Conversation::class)))
            ->toResponse($request);

        $response->headers->set('x-conversation-id', $conversationId);

        return $response;
    }

    /**
     * Throttle chat turns after their request payload passes validation.
     */
    protected function limit(RequestSignature|null $signature = null): Limit
    {
        $signature = $signature instanceof RequestSignature ? $signature : RequestSignature::default();

        return Limit::perMinute(30)->by($signature->hash());
    }

    /**
     * Convert the public approval payload into official SDK decisions.
     *
     * @param array<array-key, mixed> $payload
     */
    private function decisions(array $payload): Decisions
    {
        $decisions = [];

        foreach ($payload as $toolCallId => $value) {
            $decision = Typer::assertArray($value);
            $action = Typer::assertString($decision['action'] ?? null);
            $arguments = $decision['arguments'] ?? null;

            $decisions[Typer::assertString($toolCallId)] = match (true) {
                $action === 'approve' && \is_array($arguments) => Decision::edit($this->notificationArguments($arguments)),
                $action === 'approve' => Decision::approve(),
                default => Decision::reject(Typer::assertNullableString($decision['result'] ?? null)),
            };
        }

        return Decisions::from($decisions);
    }

    /**
     * Normalize edited notification arguments after validation.
     *
     * @param array<array-key, mixed> $arguments
     *
     * @return array{subject: string, message: string}
     */
    private function notificationArguments(array $arguments): array
    {
        return [
            'subject' => Typer::assertString($arguments['subject'] ?? null),
            'message' => Typer::assertString($arguments['message'] ?? null),
        ];
    }
}
