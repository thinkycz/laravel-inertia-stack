<?php

declare(strict_types=1);

use App\Models\User;
use Database\Factories\UserFactory;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Laravel\Ai\Models\Conversation;
use Laravel\Ai\Models\ConversationMessage;
use Thinkycz\LaravelCore\Models\DatabaseToken;
use Thinkycz\LaravelCore\Support\Config;

\test('legacy token upgrades preserve credentials and give a thirty-day transition', function (): void {
    $user = UserFactory::new()->createOne();
    $token = DatabaseToken::inject()->login('users', $user);
    $hash = DB::table('database_tokens')->where('id', $token->getKey())->value('hash');
    Schema::table('database_tokens', static function (Blueprint $table): void {
        $table->dropIndex(['expires_at']);
        $table->dropColumn(['expires_at', 'last_used_at', 'user_agent']);
    });
    $migration = require \database_path('migrations/2026_09_30_070207_add_session_metadata_to_database_tokens_table.php');
    $migration->up();
    \expect(DB::table('database_tokens')->where('id', $token->getKey())->value('hash'))->toBe($hash);
    \expect($token->refresh()->getExpiresAt()?->diffInDays(\now(), true))->toBeBetween(29.99, 30.01);
    \expect(DatabaseToken::inject()->findByBearer($token->bearer))->not->toBeNull();
});

\test('SDK upgrade preserves legacy conversation messages and pending approval data', function (): void {
    $user = UserFactory::new()->createOne();
    $conversation = $user->conversations()->create(['id' => Str::uuid()->toString(), 'title' => 'Retained history']);
    $id = Str::uuid()->toString();
    DB::table('agent_conversation_messages')->insert([
        'id' => $id, 'conversation_id' => $conversation->getKey(), 'participant_type' => User::class, 'participant_id' => $user->getKey(), 'agent' => 'legacy-agent', 'role' => 'assistant', 'content' => 'Original text',
        'attachments' => '[]', 'usage' => '[]', 'meta' => '[]',
        'tool_calls' => \json_encode([['id' => 'call-1', 'name' => 'SendTestNotificationTool', 'arguments' => ['subject' => 'Kept', 'message' => 'Kept message']]]),
        'tool_results' => '[]', 'approval_state' => \json_encode(['pending' => ['call-1' => 'Confirm delivery']]),
        'created_at' => \now(), 'updated_at' => \now(),
    ]);
    $migration = require \database_path('migrations/2026_09_30_070903_upgrade_ai_turn_storage.php');
    $migration->up();
    $message = ConversationMessage::query()->findOrFail($id);
    \expect($message->getAttribute('content'))->toBe('Original text');
    \expect($message->getAttribute('status')->value)->toBe('paused');
    \expect($message->tool_calls[0]['arguments']['subject'])->toBe('Kept');
    \expect($message->tool_calls[0]['approval_reason'])->toBe('Confirm delivery');
    \expect(Conversation::query()->findOrFail($conversation->getKey())->getAttribute('title'))->toBe('Retained history');
});

\test('incompatible legacy conversation schemas fail before modifying data', function (): void {
    Config::inject()->assign('ai.conversations.tables.conversations', 'legacy_conversations');
    Config::inject()->assign('ai.conversations.tables.messages', 'legacy_messages');
    Schema::create('legacy_conversations', static function (Blueprint $table): void { $table->string('id');
        $table->string('title'); });
    DB::table('legacy_conversations')->insert(['id' => 'keep', 'title' => 'Keep this history']);
    $migration = require \database_path('migrations/2026_08_11_000000_reset_agent_conversations_for_ai_sdk.php');
    \expect(static fn() => $migration->up())->toThrow(RuntimeException::class, 'No tables were changed');
    \expect(DB::table('legacy_conversations')->value('title'))->toBe('Keep this history');
    \expect(Schema::hasTable('legacy_messages'))->toBeFalse();
    Schema::drop('legacy_conversations');
});
