<?php

declare(strict_types=1);

namespace Thinkycz\LaravelCore\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Thinkycz\LaravelCore\Support\Config;
use Thinkycz\LaravelCore\Support\Resolver;
use Thinkycz\LaravelCore\Support\Typer;

class DatabaseToken extends BaseModel
{
    /**
     * Plain text bearer.
     */
    public string|null $bearer = null;

    /**
     * @inheritDoc
     */
    protected $hidden = ['hash', 'bearer'];

    /**
     * Inject.
     */
    public static function inject(): self
    {
        return new self();
    }

    /**
     * Find database token matching given bearer.
     */
    public function findByBearer(string $bearer): static|null
    {
        if (!\str_contains($bearer, '|')) {
            return null;
        }

        [$id, $token] = \explode('|', $bearer, 2);

        $key = \filter_var($id, \FILTER_VALIDATE_INT);

        if ($key === false) {
            return null;
        }

        $instance = static::findByKey($key);

        if ($instance === null) {
            return null;
        }

        if (!\hash_equals($instance->mustString('hash'), \hash('sha256', $token))) {
            return null;
        }

        if ($instance->getExpiresAt()?->isFuture() !== true) {
            return null;
        }

        if ($instance->getLastUsedAt() === null || $instance->getLastUsedAt()->lt(Carbon::now()->subMinutes(5))) {
            $instance->setAttribute('last_used_at', Carbon::now());
            $instance->save();
        }

        return $instance;
    }

    /**
     * Login user.
     */
    public function login(string $guardName, BaseUser $user): static
    {
        $token = Str::random(40);
        $hash = \hash('sha256', $token);

        $this->setAttribute('hash', $hash);
        $this->setAttribute('expires_at', Carbon::now()->addDays(Config::inject()->assertInt('auth.token_lifetime_days')));
        $this->setAttribute('last_used_at', Carbon::now());
        $this->setAttribute('user_agent', Str::limit(Resolver::resolveRequest()->userAgent() ?? '', 512, ''));

        $this->relationship($guardName, $user)->associate($user);

        $this->save();
        $this->unsetRelation('relationship');

        $this->bearer = "{$this->getKey()}|{$token}";

        return $this;
    }

    /**
     * Get the absolute expiry time.
     */
    public function getExpiresAt(): Carbon|null
    {
        return $this->assertNullableCarbon('expires_at');
    }

    /**
     * Get the last authenticated request time.
     */
    public function getLastUsedAt(): Carbon|null
    {
        return $this->assertNullableCarbon('last_used_at');
    }

    /**
     * Get the browser description without exposing bearer credentials.
     */
    public function getUserAgent(): string|null
    {
        return $this->assertNullableString('user_agent');
    }

    /**
     * Get the session creation time.
     */
    public function getCreatedAt(): Carbon
    {
        return $this->assertCarbon('created_at');
    }

    /**
     * Get user.
     */
    public function user(string $guardName): BaseUser|null
    {
        return Typer::assertNullableInstance($this->relationship($guardName, null)->getResults(), BaseUser::class);
    }

    /**
     * @inheritDoc
     */
    protected function casts(): array
    {
        return ['expires_at' => 'datetime', 'last_used_at' => 'datetime'];
    }

    /**
     * Relationship.
     *
     * @return BelongsTo<BaseUser, $this>
     */
    protected function relationship(string $guardName, BaseUser|null $user): BelongsTo
    {
        $instance = new (Config::inject()->assertA("auth.providers.{$guardName}.model", BaseUser::class))();

        return $this->belongsTo($instance::class, $instance->getForeignKey(), $instance->getKeyName(), 'relationship');
    }
}
