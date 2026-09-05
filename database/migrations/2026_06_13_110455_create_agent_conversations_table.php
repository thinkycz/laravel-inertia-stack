<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Laravel\Ai\Migrations\AiMigration;

return new class extends AiMigration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $conversationsTableVal = \config('ai.conversations.tables.conversations', 'agent_conversations');
        $conversationsTable = \is_string($conversationsTableVal) ? $conversationsTableVal : 'agent_conversations';

        $messagesTableVal = \config('ai.conversations.tables.messages', 'agent_conversation_messages');
        $messagesTable = \is_string($messagesTableVal) ? $messagesTableVal : 'agent_conversation_messages';

        Schema::create($conversationsTable, function (Blueprint $table): void {
            $table->string('id', 36)->primary();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('title');
            $table->timestamps();

            $table->index(['user_id', 'updated_at']);
        });

        Schema::create($messagesTable, function (Blueprint $table) use ($conversationsTable): void {
            $table->string('id', 36)->primary();
            $table->string('conversation_id', 36)->index();
            $table->foreign('conversation_id')->references('id')->on($conversationsTable)->cascadeOnDelete();
            $table->string('run_id', 36)->nullable()->index();
            $table->string('status')->default('completed');
            $table->text('clarification_answer')->nullable();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('agent');
            $table->string('role', 25);
            $table->longText('content');
            $table->text('attachments');
            $table->text('tool_calls');
            $table->text('tool_results');
            $table->text('usage');
            $table->text('meta');
            $table->timestamps();

            $table->index(['conversation_id', 'user_id', 'updated_at'], 'conversation_index');
            $table->index(['user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $messagesTableVal = \config('ai.conversations.tables.messages', 'agent_conversation_messages');
        $messagesTable = \is_string($messagesTableVal) ? $messagesTableVal : 'agent_conversation_messages';

        $conversationsTableVal = \config('ai.conversations.tables.conversations', 'agent_conversations');
        $conversationsTable = \is_string($conversationsTableVal) ? $conversationsTableVal : 'agent_conversations';

        Schema::dropIfExists($messagesTable);
        Schema::dropIfExists($conversationsTable);
    }
};
