<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Laravel\Ai\Migrations\AiMigration;
use Thinkycz\LaravelCore\Support\Config;
use Thinkycz\LaravelCore\Support\Resolver;
use Thinkycz\LaravelCore\Support\Typer;

return new class extends AiMigration {
    /**
     * Preserve legacy messages while adapting them to the SDK 1 turn format.
     */
    public function up(): void
    {
        $table = Config::inject()->assertString('ai.conversations.tables.messages');
        $schema = Resolver::resolveSchemaBuilder();
        if (!$schema->hasColumn($table, 'steps')) {
            $schema->table($table, static function (Blueprint $blueprint): void {
                $blueprint->longText('steps')->nullable();
                $blueprint->string('status', 25)->default('completed');
                $blueprint->text('tool_calls')->nullable()->change();
                $blueprint->text('tool_results')->nullable()->change();
            });
        }

        foreach (DB::table($table)->whereNull('steps')->lazyById(100) as $row) {
            $calls = $this->decode($row->tool_calls);
            $results = $this->decode($row->tool_results);
            $state = $this->decode($row->approval_state);
            $pending = Typer::assertArray($state['pending'] ?? []);
            $converted = [];
            foreach ($calls as $value) {
                $call = Typer::assertArray($value);
                $id = Typer::assertString($call['id']);
                if (\array_key_exists($id, $pending)) {
                    $call['approval_reason'] = $pending[$id];
                }
                foreach ($results as $resultValue) {
                    $result = Typer::assertArray($resultValue);
                    if (($result['id'] ?? null) === $id) {
                        $call = [...$call, 'result' => $result['result'] ?? '', 'denied' => $result['denied'] ?? false, 'failed' => $result['failed'] ?? false];
                    }
                }
                $converted[] = $call;
            }
            DB::table($table)->where('id', $row->id)->update([
                'steps' => \json_encode([['content' => $row->content, 'tool_calls' => $converted, 'reasoning' => '', 'replay_blocks' => [], 'provider_tool_calls' => []]], \JSON_THROW_ON_ERROR),
                'status' => $pending === [] ? 'completed' : 'paused',
            ]);
        }
    }

    /**
     * Decode persisted legacy JSON without silently discarding malformed data.
     *
     * @return array<array-key, mixed>
     */
    private function decode(mixed $value): array
    {
        return $value === null ? [] : Typer::assertArray(\json_decode(Typer::assertString($value), true, 512, \JSON_THROW_ON_ERROR));
    }
};
