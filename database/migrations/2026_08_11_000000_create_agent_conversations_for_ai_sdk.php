<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Laravel\Ai\Migrations\AiMigration;
use Thinkycz\LaravelCore\Support\Config;
use Thinkycz\LaravelCore\Support\Resolver;

return new class extends AiMigration
{
    /**
     * Create the official SDK conversation schema for fresh installations.
     */
    public function up(): void
    {
        $schema = Resolver::resolveSchemaBuilder();
        $messagesTable = Config::inject()->parseNullableString('ai.conversations.tables.messages') ?? 'agent_conversation_messages';
        $conversationsTable = Config::inject()->parseNullableString('ai.conversations.tables.conversations') ?? 'agent_conversations';

        $schema->create($conversationsTable, static function (Blueprint $table): void {
            $table->string('id', 36)->primary();
            $table->string('participant_type')->nullable();
            $table->unsignedBigInteger('participant_id')->nullable();
            $table->string('title');
            $table->timestamps();

            $table->index(['participant_type', 'participant_id', 'updated_at'], 'participant_updated_at_index');
        });

        $schema->create($messagesTable, static function (Blueprint $table) use ($conversationsTable): void {
            $table->string('id', 36)->primary();
            $table->string('conversation_id', 36)->index();
            $table->foreign('conversation_id')->references('id')->on($conversationsTable)->cascadeOnDelete();
            $table->string('participant_type')->nullable();
            $table->unsignedBigInteger('participant_id')->nullable();
            $table->string('agent');
            $table->string('role', 25);
            $table->longText('content');
            $table->text('attachments');
            $table->text('tool_calls');
            $table->text('tool_results');
            $table->text('usage');
            $table->text('meta');
            $table->text('approval_state')->nullable();
            $table->timestamps();

            $table->index(['conversation_id', 'participant_type', 'participant_id', 'updated_at'], 'conversation_index');
            $table->index(['participant_type', 'participant_id'], 'participant_index');
        });
    }

    /**
     * Drop the official conversation schema.
     */
    public function down(): void
    {
        $schema = Resolver::resolveSchemaBuilder();
        $schema->dropIfExists(Config::inject()->parseNullableString('ai.conversations.tables.messages') ?? 'agent_conversation_messages');
        $schema->dropIfExists(Config::inject()->parseNullableString('ai.conversations.tables.conversations') ?? 'agent_conversations');
    }
};
