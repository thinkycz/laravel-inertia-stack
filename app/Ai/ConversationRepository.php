<?php

declare(strict_types=1);

namespace App\Ai;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Ai\Messages\Message;
use Laravel\Ai\Models\Conversation;
use Thinkycz\LaravelCore\Support\Config;
use Thinkycz\LaravelCore\Support\Typer;

class ConversationRepository
{
    /**
     * Create a conversation owned by the given user.
     */
    public function createForUser(User $user, string $message): Conversation
    {
        return Conversation::create([
            'id' => Str::uuid()->toString(),
            'user_id' => $this->userId($user),
            'title' => Str::limit($message, 35),
        ]);
    }

    /**
     * Find a conversation that belongs to the given user.
     */
    public function findOwned(string $id, User $user): Conversation|null
    {
        $conversation = Conversation::find($id);

        if ($conversation === null) {
            return null;
        }

        if ($this->userId($user) !== Typer::assertNullableInt($conversation->getAttribute('user_id'))) {
            return null;
        }

        return $conversation;
    }

    /**
     * Delete a conversation and its stored messages.
     */
    public function delete(Conversation $conversation): void
    {
        DB::transaction(function () use ($conversation): void {
            DB::table($this->messagesTable())
                ->where('conversation_id', $this->conversationId($conversation))
                ->delete();

            $conversation->delete();
        });
    }

    /**
     * Delete the conversation only when no messages were persisted.
     */
    public function deleteIfEmpty(Conversation $conversation): void
    {
        if ($this->hasMessages($conversation)) {
            return;
        }

        $this->delete($conversation);
    }

    /**
     * Serialize a conversation for the dashboard page.
     *
     * @param iterable<Message> $messages
     *
     * @return array{id: string, title: string, messages: array<int, array{role: string, content: string|null}>}
     */
    public function dashboardPayload(Conversation $conversation, iterable $messages): array
    {
        return [
            'id' => $this->conversationId($conversation),
            'title' => $this->title($conversation),
            'messages' => $this->messagesPayload($messages),
        ];
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
     * Determine whether the conversation has persisted messages.
     */
    private function hasMessages(Conversation $conversation): bool
    {
        return DB::table($this->messagesTable())
            ->where('conversation_id', $this->conversationId($conversation))
            ->exists();
    }

    /**
     * Serialize messages for the dashboard page.
     *
     * @param iterable<Message> $messages
     *
     * @return array<int, array{role: string, content: string|null}>
     */
    private function messagesPayload(iterable $messages): array
    {
        $payload = [];

        foreach ($messages as $message) {
            $payload[] = [
                'role' => $message->role->value,
                'content' => Typer::assertNullableString($message->content),
            ];
        }

        return $payload;
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
}
