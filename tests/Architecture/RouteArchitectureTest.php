<?php

declare(strict_types=1);

require_once __DIR__ . '/Helpers.php';

\arch('routes/api.php uses Resolver::resolveRouteRegistrar exclusively', function (): void {
    $contents = (string) \file_get_contents(\base_path('routes/api.php'));

    \expect($contents)
        ->not->toContain('Route::')
        ->toContain('Resolver::resolveRouteRegistrar()');

    \expect(\mb_substr_count($contents, 'Resolver::resolveRouteRegistrar()'))->toBeGreaterThanOrEqual(4);
});

\arch('routes/api.php only uses GET and POST verbs', function (): void {
    $contents = (string) \file_get_contents(\base_path('routes/api.php'));

    \expect($contents)
        ->not->toContain('->put(')
        ->not->toContain('->patch(')
        ->not->toContain('->delete(')
        ->not->toContain('->any(')
        ->not->toContain('->match(');
});

\arch('routes/api.php registers routes under a v1/... prefix', function (): void {
    $contents = (string) \file_get_contents(\base_path('routes/api.php'));

    \expect(\mb_substr_count($contents, '->prefix(\'v1/'))->toBeGreaterThanOrEqual(4);
});

\arch('routes/api.php has no path parameters (no {id} or similar)', function (): void {
    $contents = (string) \file_get_contents(\base_path('routes/api.php'));

    \preg_match_all('/->(?:get|post)\\(\\s*[\'"]([^\'"]*)[\'"]/', $contents, $matches);

    foreach ($matches[1] as $path) {
        \expect($path)
            ->not->toMatch('/\\{[^}]+\\}/');
    }
});

\arch('routes/web.php uses Resolver::resolveRouteRegistrar exclusively', function (): void {
    $contents = (string) \file_get_contents(\base_path('routes/web.php'));

    \expect($contents)
        ->not->toContain('Route::')
        ->toContain('Resolver::resolveRouteRegistrar()');
});
