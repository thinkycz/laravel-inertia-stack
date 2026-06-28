<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Thinkycz\LaravelCore\Models\BaseModel;

class AgentRunEvent extends BaseModel
{
    public const string TYPE_RUN_STARTED = 'run_started';

    public const string TYPE_TEXT_DELTA = 'text_delta';

    public const string TYPE_TOOL_ACTIVITY = 'tool_activity';

    public const string TYPE_RUN_COMPLETED = 'run_completed';

    public const string TYPE_RUN_FAILED = 'run_failed';

    public const string TYPE_RUN_CANCELLED = 'run_cancelled';

    /**
     * Base select query.
     *
     * @param Builder<static> $builder
     */
    public static function querySelect(Builder $builder): void
    {
        $builder->getQuery()->select($builder->qualifyColumn('*'));
    }

    /**
     * Search scope.
     *
     * @param Builder<static> $builder
     */
    public static function scopeSearch(Builder $builder, string $search): void
    {
        $builder->getQuery()->where($builder->qualifyColumn('type'), 'LIKE', "%{$search}%");
    }

    /**
     * Id getter.
     */
    public function getId(): int
    {
        return $this->assertInt('id');
    }

    /**
     * Run id getter.
     */
    public function getRunId(): string
    {
        return $this->assertString('run_id');
    }

    /**
     * Type getter.
     */
    public function getType(): string
    {
        return $this->assertString('type');
    }

    /**
     * Payload getter.
     *
     * @return array<string, mixed>
     */
    public function getPayload(): array
    {
        $payload = $this->mixed('payload');
        if (!\is_array($payload)) {
            return [];
        }

        $normalized = [];
        foreach ($payload as $key => $value) {
            if (\is_string($key)) {
                $normalized[$key] = $value;
            }
        }

        return $normalized;
    }

    /**
     * Run relationship.
     *
     * @return BelongsTo<AgentRun, $this>
     */
    public function run(): BelongsTo
    {
        return $this->belongsTo(AgentRun::class, 'run_id');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payload' => 'array',
        ];
    }
}
