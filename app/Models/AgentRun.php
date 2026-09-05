<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Thinkycz\LaravelCore\Models\BaseModel;

class AgentRun extends BaseModel
{
    public const string STATUS_QUEUED = 'queued';

    public const string STATUS_RUNNING = 'running';

    public const string STATUS_COMPLETED = 'completed';

    public const string STATUS_FAILED = 'failed';

    public const string STATUS_CANCELLED = 'cancelled';

    /**
     * Indicates if the model's ID is auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = false;

    /**
     * The data type of the primary key ID.
     *
     * @var string
     */
    protected $keyType = 'string';

    /**
     * The attributes that aren't mass assignable.
     *
     * @var array<string>
     */
    protected $guarded = [];

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
        $builder->getQuery()->where($builder->qualifyColumn('prompt'), 'LIKE', "%{$search}%");
    }

    /**
     * Active run statuses.
     *
     * @return array<int, string>
     */
    public static function activeStatuses(): array
    {
        return [self::STATUS_QUEUED, self::STATUS_RUNNING];
    }

    /**
     * Id getter.
     */
    public function getId(): string
    {
        return $this->assertString('id');
    }

    /**
     * Conversation id getter.
     */
    public function getConversationId(): string
    {
        return $this->assertString('conversation_id');
    }

    /**
     * User id getter.
     */
    public function getUserId(): int
    {
        return $this->assertInt('user_id');
    }

    /**
     * Status getter.
     */
    public function getStatus(): string
    {
        return $this->assertString('status');
    }

    /**
     * Prompt getter.
     */
    public function getPrompt(): string
    {
        return $this->assertString('prompt');
    }

    /**
     * User message id getter.
     */
    public function getUserMessageId(): string
    {
        return $this->assertString('user_message_id');
    }

    /**
     * Assistant message id getter.
     */
    public function getAssistantMessageId(): string|null
    {
        return $this->assertNullableString('assistant_message_id');
    }

    /**
     * Assistant content getter.
     */
    public function getAssistantContent(): string
    {
        return $this->assertNullableString('assistant_content') ?? '';
    }

    /**
     * Cursor committed with the content snapshot.
     */
    public function getLastEventId(): int
    {
        return $this->assertInt('last_event_id');
    }

    /**
     * Error getter.
     */
    public function getError(): string|null
    {
        return $this->assertNullableString('error');
    }

    /**
     * Whether the run is queued or running.
     */
    public function isActive(): bool
    {
        return \in_array($this->getStatus(), self::activeStatuses(), true);
    }

    /**
     * Run events relationship.
     *
     * @return HasMany<AgentRunEvent, $this>
     */
    public function events(): HasMany
    {
        return $this->hasMany(AgentRunEvent::class, 'run_id');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }
}
