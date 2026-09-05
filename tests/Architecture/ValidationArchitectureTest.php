<?php

declare(strict_types=1);

require_once __DIR__ . '/Helpers.php';

use Thinkycz\LaravelCore\Validation\AuthValidity;

\arch('validity classes inherit AuthValidity and keep presence rules at callers', function (): void {
    foreach (\arch_php_files(\base_path('app/Http/Validation')) as $file) {
        $class = 'App\\' . \str_replace('/', '\\', \mb_substr($file, \mb_strlen(\app_path()) + 1, -4));
        \expect($class)->toEndWith('Validity');
        \expect(\is_subclass_of($class, AuthValidity::class))->toBeTrue();
        $contents = (string) \file_get_contents($file);
        \expect($contents)->not->toMatch('/->(?:required|nullable)\\(\\)/');
    }
    \expect(AuthValidity::inject())->toBeInstanceOf(AuthValidity::class);
});
