<?php

declare(strict_types=1);

namespace App\Models;

use App\Http\Resources\UserResource;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\Resources\JsonApi\JsonApiResource;
use Illuminate\Support\Carbon;
use Laravel\Ai\Concerns\HasConversations;
use Thinkycz\LaravelCore\Models\BaseUser;

class User extends BaseUser implements MustVerifyEmail
{
    use HasConversations;

    /**
     * Explicit mass-assignment allowlist.
     *
     * Overrides the permissive `BaseUser::$guarded = ['id']` so only the
     * columns actually written through `create()`/`update()` are
     * fillable: `email`, `password`, and `locale`. Sensitive attributes
     * such as `email_verified_at` and `remember_token` are set through
     * `forceFill()`/`save()` (bypassing the guard) by their dedicated
     * methods, so they remain intentionally non-fillable here.
     *
     * @var list<string>
     */
    protected $fillable = ['email', 'password', 'locale'];

    /**
     * Email getter.
     */
    public function getEmail(): string
    {
        return $this->assertString('email');
    }

    /**
     * Locale getter.
     */
    public function getLocale(): string
    {
        return $this->assertString('locale');
    }

    /**
     * EmailVerifiedAt getter.
     */
    public function getEmailVerifiedAt(): Carbon|null
    {
        return $this->assertNullableCarbon('email_verified_at');
    }

    /**
     * @inheritDoc
     */
    public function markEmailAsUnverified(): bool
    {
        return $this->forceFill([
            'email_verified_at' => null,
        ])->save();
    }

    /**
     * Me resource.
     */
    public function meResource(): JsonApiResource
    {
        return new UserResource($this);
    }

    /**
     * VND json:api resource.
     */
    public function resource(): JsonApiResource
    {
        return $this->meResource();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
