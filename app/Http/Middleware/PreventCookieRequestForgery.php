<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Thinkycz\LaravelCore\Models\DatabaseToken;

class PreventCookieRequestForgery extends PreventRequestForgery
{
    /**
     * Only a verified explicit bearer credential can bypass cookie CSRF checks.
     */
    protected function tokensMatch(mixed $request): bool
    {
        if (($bearer = $request->bearerToken()) !== null) {
            if (DatabaseToken::inject()->findByBearer($bearer)?->user('users') !== null) {
                return true;
            }
        }

        return parent::tokensMatch($request);
    }
}
