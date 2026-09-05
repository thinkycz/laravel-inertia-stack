<?php

declare(strict_types=1);

namespace Thinkycz\LaravelCore\Support;

use Closure;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Http\Exceptions\ThrottleRequestsException;

class ThrottleSupport
{
    /**
     * Share delivery quotas across HTTP surfaces while keeping login budgets separate.
     */
    public static function delivery(string $operation, string $email): void
    {
        $address = \hash('sha256', \mb_strtolower(\trim($email)));
        self::operation(Limit::perMinute(10)->by('delivery:ip:' . (Resolver::resolveRequest()->ip() ?? 'unknown')), static function (): void {});
        self::operation(Limit::perMinute(1)->by("delivery:{$operation}:{$address}"), static function (): void {});
    }

    /**
     * Serialize successful operation accounting across concurrent requests.
     *
     * @template T
     *
     * @param Closure(): T $operation
     *
     * @return T
     */
    public static function operation(Limit $limit, Closure $operation): mixed
    {
        $store = Typer::assertInstance(Resolver::resolveCacheManager()->store()->getStore(), LockProvider::class);
        $lock = $store->lock('quota:' . Typer::assertString($limit->key), 180);
        $lock->block(5);
        try {
            [$hit] = self::throttle($limit);
            $result = $operation();
            $hit();

            return $result;
        } finally {
            $lock->release();
        }
    }

    /**
     * Register throttle.
     *
     * @param (Closure(int): never)|null $onError
     *
     * @return array{Closure(): void, Closure(): void}
     */
    public static function throttle(Limit $limit, Closure|null $onError = null): array
    {
        $hash = Typer::assertString($limit->key);

        $key = "throttle:{$hash}";

        $rateLimiter = Resolver::resolveRateLimiter();

        $failed = $rateLimiter->tooManyAttempts($key, $limit->maxAttempts);

        if ($failed) {
            if ($onError === null) {
                throw new ThrottleRequestsException();
            }

            $onError($rateLimiter->availableIn($key));
        }

        return [
            static function () use ($key, $rateLimiter, $limit): void {
                $rateLimiter->hit($key, $limit->decaySeconds);
            },
            static function () use ($key, $rateLimiter): void {
                $rateLimiter->clear($key);
            },
        ];
    }

    /**
     * Register throttle and hit.
     *
     * @param (Closure(int): never)|null $onError
     *
     * @return Closure(): void
     */
    public static function hit(Limit $limit, Closure|null $onError = null): Closure
    {
        [$hit, $clear] = static::throttle($limit, $onError);

        $hit();

        return $clear;
    }
}
