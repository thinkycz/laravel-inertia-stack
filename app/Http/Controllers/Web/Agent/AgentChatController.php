<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Agent;

use App\Ai\Agents\ChatAgent;
use App\Ai\ConversationRepository;
use App\Ai\SafeChatResponse;
use App\Http\Controllers\Web\Concerns\ValidatesWebRequests;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Laravel\Ai\Approvals\Decision;
use Laravel\Ai\Approvals\Decisions;
use Laravel\Ai\Contracts\ConversationStore;
use Laravel\Ai\Models\Conversation;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Thinkycz\LaravelCore\Support\Resolver;
use Thinkycz\LaravelCore\Support\ThrottleSupport;
use Thinkycz\LaravelCore\Support\Typer;
use Throwable;

class AgentChatController
{
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

        $conversation = Typer::assertInstance($conversation, Conversation::class);
        $conversationId = $conversations->conversationId($conversation);
        $store = Typer::assertInstance(Resolver::resolveCacheManager()->store()->getStore(), LockProvider::class);
        $lock = $store->lock('agent-chat:' . $conversationId, 180);
        if ($lock->get() !== true) {
            return Resolver::resolveResponseFactory()->json(['message' => 'agent.conversation_busy'], 409);
        }

        try {
            $pendingMessageId = $conversations->latestPendingMessageId($conversation);
            $decisionPayload = $validated->parseNullableArray('decisions');
            if ($decisionPayload !== null) {
                if ($pendingMessageId === null) {
                    $lock->release();

                    return Resolver::resolveResponseFactory()->json(['message' => 'agent.approval.invalid'], 409);
                }
                $pendingMessage = $conversation->messages()->whereKey($pendingMessageId)->firstOrFail();
                $state = Typer::assertArray($pendingMessage->getAttribute('approval_state'));
                $pending = Typer::assertArray($state['pending'] ?? []);
                if (\array_diff_key($decisionPayload, $pending) !== [] || \array_diff_key($pending, $decisionPayload) !== [] || \array_filter(\array_keys($decisionPayload), static fn($key): bool => !\is_string($key)) !== []) {
                    $lock->release();

                    return Resolver::resolveResponseFactory()->json(['message' => 'agent.approval.invalid'], 422);
                }
            } elseif ($pendingMessageId !== null) {
                $lock->release();

                return Resolver::resolveResponseFactory()->json(['message' => 'agent.approval.invalid'], 409);
            }

            $agent = ChatAgent::make()->continue($conversationId, $user);
            $prompt = $message ?? $this->decisions($decisionPayload ?? []);
            $response = ThrottleSupport::operation(
                Limit::perMinute(30)->by('ai:chat:' . $user->getKey()),
                fn(): Response => (new SafeChatResponse($agent->stream($prompt, timeout: 90), $conversationId, $pendingMessageId))->toResponse($request),
            );
            $response = Typer::assertInstance($response, StreamedResponse::class);
            $callback = Typer::assertNotNull($response->getCallback());
            $response->setCallback(static function () use ($callback, $lock): void {
                $previousAbortSetting = \ignore_user_abort(true);
                try {
                    $callback();
                } catch (Throwable $exception) {
                    \report($exception);
                    echo 'data: ' . \json_encode(['type' => 'error', 'errorText' => 'ai.errors.provider_failed'], \JSON_THROW_ON_ERROR) . "\n\n";
                    echo "data: [DONE]\n\n";
                } finally {
                    $lock->release();
                    \ignore_user_abort($previousAbortSetting !== 0);
                }
            });
            $response->headers->set('x-conversation-id', $conversationId);

            return $response;
        } catch (Throwable $exception) {
            $lock->release();
            if ($exception instanceof HttpExceptionInterface) {
                throw $exception;
            }
            \report($exception);

            return Resolver::resolveResponseFactory()->json(['message' => 'ai.errors.provider_failed'], 502);
        }
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
