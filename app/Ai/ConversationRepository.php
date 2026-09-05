<?php

declare(strict_types=1);

namespace App\Ai;

use App\Models\AgentRun;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Laravel\Ai\Messages\AssistantMessage;
use Laravel\Ai\Messages\Message;
use Laravel\Ai\Messages\ToolResultMessage;
use Laravel\Ai\Models\Conversation;
use Laravel\Ai\Models\ConversationMessage;
use Laravel\Ai\Responses\Data\ToolCall;
use Laravel\Ai\Responses\Data\ToolResult;
use Thinkycz\LaravelCore\Support\Config;
use Thinkycz\LaravelCore\Support\Typer;

class ConversationRepository
{
    /**
     * Maximum number of conversations surfaced in the sidebar payload.
     */
    public const int SIDEBAR_LIMIT = 50;

    /**
     * Resolve the most recently updated conversations for the sidebar.
     *
     * @return list<array{id: string, title: string, updated_at: string|null}>
     */
    public function recentForSidebar(User $user, int $limit = self::SIDEBAR_LIMIT): array
    {
        $payload = [];

        foreach ($user->conversations()->select(['id', 'title', 'updated_at'])->limit($limit)->get() as $conversation) {
            $updatedAt = $conversation->getAttribute('updated_at');

            $payload[] = [
                'id' => $this->conversationId($conversation),
                'title' => $this->title($conversation),
                'updated_at' => $updatedAt === null ? null : $this->serializeTimestamp($updatedAt),
            ];
        }

        return $payload;
    }

    /**
     * Find a conversation that belongs to the given user.
     */
    public function findOwned(string $id, User $user): Conversation|null
    {
        $conversation = Conversation::query()
            ->where('id', $id)
            ->where('user_id', $this->userId($user))
            ->first();

        return $conversation instanceof Conversation ? $conversation : null;
    }

    /**
     * Delete a conversation and its stored messages.
     */
    public function delete(Conversation $conversation): void
    {
        DB::transaction(function () use ($conversation): void {
            $conversation->newQuery()->whereKey($conversation->getKey())->lockForUpdate()->first();
            DB::table($this->messagesTable())->where('conversation_id', $this->conversationId($conversation))->delete();
            $conversation->delete();
        });
    }

    /**
     * Serialize persisted history, including partial terminal messages.
     *
     * @return array{id: string, title: string, messages: list<array<string, mixed>>}
     */
    public function dashboardPayload(Conversation $conversation): array
    {
        $messages = [];
        foreach (ConversationMessage::query()->where('conversation_id', $this->conversationId($conversation))
            ->orderByDesc('id')->limit(100)->get()->reverse() as $message) {
            $meta = Typer::assertArray($message->getAttribute('meta'));
            $messages[] = [
                'id' => Typer::assertString($message->getKey()),
                'role' => Typer::assertString($message->getAttribute('role')),
                'content' => Typer::assertString($message->getAttribute('content')),
                'status' => Typer::assertString($message->getAttribute('status')),
                'clarification' => $meta['clarification'] ?? null,
                'clarification_answer' => $message->getAttribute('clarification_answer'),
            ];
        }

        return ['id' => $this->conversationId($conversation), 'title' => $this->title($conversation), 'messages' => $messages];
    }

    /**
     * Supply completed prior exchanges only; the SDK appends the current prompt.
     *
     * @return list<Message>
     */
    public function providerMessages(AgentRun $run): array
    {
        $messages = [];
        $records = ConversationMessage::query()->where('conversation_id', $run->getConversationId())
            ->where('status', AgentRun::STATUS_COMPLETED)->where('id', '<', $run->getUserMessageId())
            ->orderByDesc('id')->limit(100)->get()->reverse();
        foreach ($records as $record) {
            $content = Typer::assertString($record->getAttribute('content'));
            if ($record->getAttribute('role') === 'user') {
                $messages[] = new Message('user', $content);

                continue;
            }
            $calls = [];
            foreach (Typer::assertArray($record->getAttribute('tool_calls')) as $call) {
                $calls[] = ToolCall::fromArray(Typer::assertArray($call));
            }
            $messages[] = new AssistantMessage($content, new Collection($calls));
            $results = [];
            foreach (Typer::assertArray($record->getAttribute('tool_results')) as $result) {
                $results[] = ToolResult::fromArray(Typer::assertArray($result));
            }
            if ($results !== []) {
                $messages[] = new ToolResultMessage(new Collection($results));
            }
        }

        return $messages;
    }

    /**
     * Resolve the conversation identifier.
     */
    public function conversationId(Conversation $conversation): string
    {
        return Typer::assertString($conversation->getKey());
    }

    /**
     * Resolve the configured messages table name.
     */
    public function messagesTable(): string
    {
        return Config::inject()->parseNullableString('ai.conversations.tables.messages') ?? 'agent_conversation_messages';
    }

    /**
     * Resolve the conversation title.
     */
    private function title(Conversation $conversation): string
    {
        return Typer::assertString($conversation->getAttribute('title'));
    }

    /**
     * Resolve the user identifier used by the AI SDK conversation store.
     */
    private function userId(User $user): int
    {
        return Typer::assertInt($user->getKey());
    }

    /**
     * Serialize a timestamp attribute to its JSON representation.
     *
     * Eloquent casts `updated_at` to a Carbon instance; the sidebar payload
     * expects the same string form the model's `toArray()` would emit.
     */
    private function serializeTimestamp(mixed $value): string
    {
        if (\is_string($value)) {
            return $value;
        }

        return Typer::assertString(Typer::assertInstance($value, Carbon::class)->toJSON());
    }
}
