<?php

declare(strict_types=1);

namespace App\Ai;

use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Laravel\Ai\Enums\MessageStatus;
use Laravel\Ai\Models\Conversation;
use Laravel\Ai\Models\ConversationMessage;
use Thinkycz\LaravelCore\Support\Typer;

class ConversationRepository
{
    /**
     * Maximum number of conversations surfaced in the sidebar.
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

        foreach ($user->conversations()->select(['id', 'title', 'updated_at'])->orderByDesc('updated_at')->limit($limit)->get() as $conversation) {
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
     * Find a conversation through the participant's official polymorphic relation.
     */
    public function findOwned(string $id, User $user): Conversation|null
    {
        $conversation = $user->conversations()->whereKey($id)->first();

        return $conversation instanceof Conversation ? $conversation : null;
    }

    /**
     * Delete a conversation and its stored SDK messages.
     */
    public function delete(Conversation $conversation): void
    {
        DB::transaction(static function () use ($conversation): void {
            $conversation->messages()->delete();
            $conversation->delete();
        });
    }

    /**
     * Serialize a conversation and its stored SDK messages for the Vue AI client.
     *
     * @return array{id: string, title: string, messages: list<array{id: string, role: string, parts: list<array<string, mixed>>}>}
     */
    public function dashboardPayload(Conversation $conversation): array
    {
        $messages = [];

        foreach ($conversation->messages()->orderBy('id')->get() as $message) {
            $messages[] = $this->messagePayload($message);
        }

        return [
            'id' => $this->conversationId($conversation),
            'title' => $this->title($conversation),
            'messages' => $messages,
        ];
    }

    /**
     * Resolve the latest message that still contains pending approvals.
     */
    public function latestPendingMessageId(Conversation $conversation): string|null
    {
        $message = $conversation->messages()
            ->where('status', MessageStatus::Paused)
            ->orderByDesc('id')
            ->first();

        if (!$message instanceof ConversationMessage) {
            return null;
        }

        return Typer::assertString($message->getKey());
    }

    /**
     * Resolve the conversation identifier.
     */
    public function conversationId(Conversation $conversation): string
    {
        return Typer::assertString($conversation->getKey());
    }

    /**
     * Serialize one stored SDK message as a Vercel UI message.
     *
     * @return array{id: string, role: string, parts: list<array<string, mixed>>}
     */
    private function messagePayload(ConversationMessage $message): array
    {
        $role = Typer::assertString($message->getAttribute('role'));
        $content = Typer::assertString($message->getAttribute('content'));
        $parts = [];

        if ($content !== '') {
            $parts[] = ['type' => 'text', 'text' => $content];
        }

        if ($role === 'assistant') {
            $parts = [...$parts, ...$this->toolParts($message)];
        }

        return [
            'id' => Typer::assertString($message->getKey()),
            'role' => $role,
            'parts' => $parts,
        ];
    }

    /**
     * Serialize stored tool calls, approval state, and results.
     *
     * @return list<array<string, mixed>>
     */
    private function toolParts(ConversationMessage $message): array
    {
        $results = [];

        foreach ($this->arrayAttribute($message, 'tool_results') as $result) {
            if (\is_array($result) && \is_string($result['id'] ?? null)) {
                $results[$result['id']] = $result;
            }
        }

        $parts = [];

        foreach ($this->arrayAttribute($message, 'tool_calls') as $call) {
            if (!\is_array($call) || !\is_string($call['id'] ?? null) || !\is_string($call['name'] ?? null)) {
                continue;
            }

            $id = $call['id'];
            $input = \is_array($call['arguments'] ?? null) ? $call['arguments'] : [];
            $part = [
                'type' => 'tool-' . $call['name'],
                'toolCallId' => $id,
                'input' => $input,
            ];

            if (\array_key_exists('approval_reason', $call) && !\array_key_exists('result', $call)) {
                $parts[] = [...$part, 'state' => 'approval-requested', 'approval' => ['id' => $id]];

                continue;
            }

            $result = $results[$id] ?? null;

            if (!\is_array($result)) {
                $parts[] = [...$part, 'state' => 'input-available'];

                continue;
            }

            if (($result['denied'] ?? false) === true) {
                $parts[] = [...$part, 'state' => 'output-denied', 'approval' => [
                    'id' => $id,
                    'approved' => false,
                    'reason' => \is_string($result['result'] ?? null) ? $result['result'] : null,
                ]];

                continue;
            }

            $parts[] = [...$part, 'state' => 'output-available', 'output' => $result['result'] ?? null, 'approval' => [
                'id' => $id,
                'approved' => true,
            ]];
        }

        return $parts;
    }

    /**
     * Read an array-cast message attribute.
     *
     * @return array<array-key, mixed>
     */
    private function arrayAttribute(ConversationMessage $message, string $key): array
    {
        $value = $message->getAttribute($key);

        return \is_array($value) ? $value : [];
    }

    /**
     * Resolve the conversation title.
     */
    private function title(Conversation $conversation): string
    {
        return Typer::assertString($conversation->getAttribute('title'));
    }

    /**
     * Serialize a timestamp attribute to JSON.
     */
    private function serializeTimestamp(mixed $value): string
    {
        if (\is_string($value)) {
            return $value;
        }

        return Typer::assertString(Typer::assertInstance($value, Carbon::class)->toJSON());
    }
}
