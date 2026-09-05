<?php

declare(strict_types=1);

namespace Thinkycz\LaravelCore\Models;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\Translation\HasLocalePreference as HasLocalePreferenceContract;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as IlluminateUser;
use Illuminate\Http\Resources\JsonApi\JsonApiResource;
use Illuminate\Notifications\Notifiable;
use Thinkycz\LaravelCore\Exceptions\GenericHttpException;
use Thinkycz\LaravelCore\Notifications\EmailVerificationNotification;
use Thinkycz\LaravelCore\Notifications\PasswordInitNotification;
use Thinkycz\LaravelCore\Notifications\PasswordResetNotification;
use Thinkycz\LaravelCore\Services\EmailBrokerService;
use Thinkycz\LaravelCore\Support\Resolver;
use Thinkycz\LaravelCore\Traits\ModelTrait;

class BaseUser extends IlluminateUser implements HasLocalePreferenceContract
{
    use ModelTrait;
    use Notifiable;

    /**
     * @inheritDoc
     */
    public $preventsLazyLoading = true;

    /**
     * @inheritDoc
     */
    protected $guarded = ['id'];

    /**
     * @inheritDoc
     */
    protected $hidden = ['password', 'remember_token'];

    /**
     * User auth or null.
     */
    public static function auth(): static|null
    {
        $guardName = (new static())->getTable();

        $me = Resolver::resolveAuthManager()->guard($guardName)->user();

        if ($me instanceof static) {
            return $me;
        }

        return null;
    }

    /**
     * Mandatory user auth.
     */
    public static function mustAuth(): static
    {
        $me = static::auth();

        if ($me !== null) {
            return $me;
        }

        throw new AuthenticationException();
    }

    /**
     * Guest user.
     */
    public static function guest(): bool
    {
        $guardName = (new static())->getTable();

        return Resolver::resolveAuthManager()->guard($guardName)->guest();
    }

    /**
     * Mandatory guest user.
     */
    public static function mustGuest(): bool
    {
        if (static::guest()) {
            return true;
        }

        throw GenericHttpException::mustBeGuest();
    }

    /**
     * @inheritDoc
     */
    public function sendPasswordResetNotification(mixed $token): void
    {
        if ($this->getEmailForPasswordReset() === '') {
            return;
        }

        $this->notify(PasswordResetNotification::inject($this->getTable(), $token, $this->getEmailForPasswordReset())->locale($this->preferredLocale()));
    }

    /**
     * Send password init notification.
     */
    public function sendPasswordInitNotification(string $token): void
    {
        if ($this->getEmailForPasswordReset() === '') {
            return;
        }

        $this->notify(PasswordInitNotification::inject($this->getTable(), $token, $this->getEmailForPasswordReset())->locale($this->preferredLocale()));
    }

    /**
     * @inheritDoc
     */
    public function sendEmailVerificationNotification(): void
    {
        if ($this->getEmailForVerification() === '') {
            return;
        }

        $this->notify(
            EmailVerificationNotification::inject(
                $this->getTable(),
                EmailBrokerService::inject()->store($this->getTable(), $this->getEmailForVerification()),
                $this->getEmailForVerification(),
            )->locale($this->preferredLocale()),
        );
    }

    /**
     * @inheritDoc
     */
    public function preferredLocale(): string
    {
        return $this->mustString('locale');
    }

    /**
     * @inheritDoc
     */
    public function getAuthIdentifier(): int
    {
        return $this->getKey();
    }

    /**
     * @inheritDoc
     */
    public function getAuthPassword(): string
    {
        return $this->string('password') ?? '';
    }

    /**
     * @inheritDoc
     */
    public function getRememberToken(): string
    {
        return $this->string($this->getRememberTokenName()) ?? '';
    }

    /**
     * @inheritDoc
     */
    public function getEmailForPasswordReset(): string
    {
        return $this->string('email') ?? '';
    }

    /**
     * @inheritDoc
     */
    public function hasVerifiedEmail(): bool
    {
        return $this->carbon('email_verified_at') !== null;
    }

    /**
     * @inheritDoc
     */
    public function getEmailForVerification(): string
    {
        return $this->string('email') ?? '';
    }

    /**
     * Database token relationship.
     *
     * @return HasMany<DatabaseToken, $this>
     */
    public function databaseTokens(): HasMany
    {
        return $this->hasMany(DatabaseToken::class);
    }

    /**
     * Me resource.
     */
    public function meResource(): JsonApiResource
    {
        return new JsonApiResource($this);
    }
}
