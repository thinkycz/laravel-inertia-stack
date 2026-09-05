<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Auth;

use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Thinkycz\LaravelCore\Http\ApiFormRequest;
use Thinkycz\LaravelCore\Routing\AutomaticController;
use Thinkycz\LaravelCore\Support\Resolver;

class CsrfShowController extends AutomaticController
{
    /**
     * Initialize the session and request-forgery cookies for API clients.
     */
    public function __invoke(ApiFormRequest $request): SymfonyResponse
    {
        return Resolver::resolveResponseFactory()->noContent();
    }
}
