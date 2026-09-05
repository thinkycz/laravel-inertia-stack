<?php

declare(strict_types=1);

namespace Thinkycz\LaravelCore\Guards;

use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Contracts\Auth\Guard as GuardContract;
use Illuminate\Support\Str;
use Thinkycz\LaravelCore\Models\BaseUser;
use Thinkycz\LaravelCore\Models\DatabaseToken;
use Thinkycz\LaravelCore\Support\Config;
use Thinkycz\LaravelCore\Support\Panicker;
use Thinkycz\LaravelCore\Support\Resolver;
use Thinkycz\LaravelCore\Support\Typer;

class DatabaseTokenGuard implements GuardContract
{
    /**
     * The currently authenticated user.
     */
    public BaseUser|false|null $user = null;

    /**
     * The currently authenticated database token.
     */
    public DatabaseToken|null $databaseToken = null;

    /**
     * Create a new guard instance.
     */
    public function __construct(public string $guardName) {}

    /**
     * Get cookie name.
     */
    public function cookieName(): string
    {
        $env = Config::inject()->appEnv();

        return (\in_array($env, ['local', 'testing'], true) ? '' : '__Host-') . Str::slug(Config::inject()->assertString('app.name') . '_' . $env . "_database_token_{$this->guardName}", '_');
    }

    /**
     * @inheritDoc
     */
    public function check(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @inheritDoc
     */
    public function guest(): bool
    {
        return $this->check() === false;
    }

    /**
     * @inheritDoc
     */
    public function user(): BaseUser|null
    {
        if ($this->user === false) {
            return null;
        }

        if ($this->user !== null) {
            return $this->user;
        }

        $bearer = $this->bearer();

        if ($bearer === null) {
            return $this->user = $this->databaseToken = null;
        }

        $databaseToken = DatabaseToken::inject()->findByBearer($bearer);

        if ($databaseToken === null) {
            return $this->user = $this->databaseToken = null;
        }

        $user = $databaseToken->user($this->guardName);

        if ($user === null) {
            return $this->user = $this->databaseToken = null;
        }

        $this->databaseToken = $databaseToken;
        $this->user = $user;

        return $user;
    }

    /**
     * @inheritDoc
     */
    public function id(): int|null
    {
        return $this->user()?->getKey();
    }

    /**
     * @inheritDoc
     *
     * @param array<mixed> $credentials
     */
    public function validate(array $credentials = []): never
    {
        Panicker::panic('Assert Never');
    }

    /**
     * @inheritDoc
     */
    public function hasUser(): bool
    {
        return $this->user instanceof BaseUser;
    }

    /**
     * @inheritDoc
     */
    public function setUser(AuthenticatableContract $user): static
    {
        $this->user = Typer::assertInstance($user, BaseUser::class);

        return $this;
    }

    /**
     * Log a user into the application.
     */
    public function login(BaseUser $user): DatabaseToken
    {
        $databaseToken = DatabaseToken::inject()->login($this->guardName, $user);

        $this->databaseToken = $databaseToken;
        $this->user = $user;

        $bearer = Typer::assertNotNull($databaseToken->bearer);

        $cookieJar = Resolver::resolveCookieJar();

        $cookieJar->queue($cookieJar->forever($this->cookieName(), $bearer, '/', null, $this->cookieSecure(), true, false, $this->cookieSameSite()));

        return $databaseToken;
    }

    /**
     * Determine whether the database token cookie should be secure-only.
     */
    public function cookieSecure(): bool
    {
        return !Config::inject()->appEnvIs(['local', 'testing']);
    }

    /**
     * Determine the database token cookie SameSite policy.
     */
    public function cookieSameSite(): string
    {
        return 'Lax';
    }

    /**
     * Logout.
     */
    public function logout(): void
    {
        if ($this->databaseToken !== null) {
            $this->databaseToken->newQuery()->whereKey($this->databaseToken->getKey())->delete();
        }

        $this->databaseToken = null;

        $this->user = false;

        Resolver::resolveCookieJar()->expire($this->cookieName(), '/', null);
    }

    /**
     * Resolve bearer from request.
     */
    public function bearer(): string|null
    {
        $request = Resolver::resolveRequest();

        $bearer = $request->bearerToken();

        if ($bearer === null) {
            $bearer = $request->cookies->get($this->cookieName());
        }

        if (\is_string($bearer)) {
            return $bearer;
        }

        return null;
    }
}
