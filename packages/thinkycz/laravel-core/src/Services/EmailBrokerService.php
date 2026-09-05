<?php

declare(strict_types=1);

namespace Thinkycz\LaravelCore\Services;

use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Str;
use Thinkycz\LaravelCore\Notifications\EmailVerificationNotification;
use Thinkycz\LaravelCore\Support\Config;
use Thinkycz\LaravelCore\Support\Resolver;

class EmailBrokerService
{
    /**
     * Constructor.
     */
    protected function __construct() {}

    /**
     * Inject.
     */
    public static function inject(): self
    {
        return new self();
    }

    /**
     * Send token.
     */
    public function store(string $guard, string $email): string
    {
        $token = $this->token();

        Resolver::resolveCacheManager()->set($this->cacheKey($guard, $email), $token, $this->cacheExpiration());

        return $token;
    }

    /**
     * Validate token.
     */
    public function validate(string $guard, string $email, string $token): bool
    {
        $expected = Resolver::resolveCacheManager()->get($this->cacheKey($guard, $email));

        return \is_string($expected) && \hash_equals($expected, $token);
    }

    /**
     * Confirm email.
     */
    public function confirm(string $guard, string $email): void
    {
        Resolver::resolveCacheManager()->set($this->cacheKey($guard, $email), true, $this->cacheExpiration());
    }

    /**
     * Email is confirmed.
     */
    public function confirmed(string $guard, string $email): bool
    {
        return Resolver::resolveCacheManager()->get($this->cacheKey($guard, $email)) === true;
    }

    /**
     * Email is pending.
     */
    public function pending(string $guard, string $email): bool
    {
        return Resolver::resolveCacheManager()->has($this->cacheKey($guard, $email)) && !$this->confirmed($guard, $email);
    }

    /**
     * Send anonymous notification.
     */
    public function anonymous(string $guard, string $email, string $locale): void
    {
        (new AnonymousNotifiable())
            ->route('mail', $email)
            ->notify(EmailVerificationNotification::inject($guard, $this->store($guard, $email), $email)->locale($locale));
    }

    /**
     * Cache key.
     */
    protected function cacheKey(string $guard, string $email): string
    {
        return \sprintf("%s:{$guard}:{$email}", static::class);
    }

    /**
     * Token.
     */
    protected function token(): string
    {
        return Str::random(40);
    }

    /**
     * Token expiration in minutes.
     */
    protected function cacheExpiration(): int|null
    {
        $config = Config::inject();

        return $config->parseInt('auth.verification.expire');
    }
}
