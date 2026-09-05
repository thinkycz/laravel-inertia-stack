<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Thinkycz\LaravelCore\Support\Resolver;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Resolver::resolveSchemaBuilder()->create('agent_runs', static function (Blueprint $table): void {
            $table->string('id', 36)->primary();
            $table->string('conversation_id', 36);
            $table->foreign('conversation_id')->references('id')->on('agent_conversations')->cascadeOnDelete();
            $table->string('active_conversation_id', 36)->nullable()->unique();
            $table->unsignedBigInteger('last_event_id')->default(0);
            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->string('status');
            $table->text('prompt');
            $table->string('user_message_id', 36);
            $table->string('assistant_message_id', 36)->nullable();
            $table->longText('assistant_content')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['conversation_id', 'status'], 'agent_runs_conversation_status_idx');
            $table->index(['user_id', 'status'], 'agent_runs_user_status_idx');
        });

        Resolver::resolveSchemaBuilder()->table('agent_conversation_messages', static function (Blueprint $table): void {
            $table->foreign('run_id')->references('id')->on('agent_runs')->cascadeOnDelete();
        });

        Resolver::resolveSchemaBuilder()->create('agent_run_events', static function (Blueprint $table): void {
            $table->id();
            $table->string('run_id', 36);
            $table->foreign('run_id')->references('id')->on('agent_runs')->cascadeOnDelete();
            $table->string('type');
            $table->json('payload');
            $table->timestamps();

            $table->index(['run_id', 'id'], 'agent_run_events_run_id_id_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Resolver::resolveSchemaBuilder()->table('agent_conversation_messages', static function (Blueprint $table): void {
            $table->dropForeign(['run_id']);
        });
        Resolver::resolveSchemaBuilder()->dropIfExists('agent_run_events');
        Resolver::resolveSchemaBuilder()->dropIfExists('agent_runs');
    }
};
