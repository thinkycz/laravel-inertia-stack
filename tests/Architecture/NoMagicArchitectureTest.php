<?php

declare(strict_types=1);

require_once __DIR__ . '/Helpers.php';

\arch('migrations do not call Blueprint polymorphic relation methods', function (): void {
    $forbidden = [
        '->morphs(',
        '->nullableMorphs(',
        '->nullableNumericMorphs(',
        '->nullableUlidMorphs(',
        '->nullableUuidMorphs(',
        '->numericMorphs(',
        '->ulidMorphs(',
        '->uuidMorphs(',
    ];

    foreach (\arch_php_files(\base_path('database/migrations')) as $file) {
        $contents = (string) \file_get_contents($file);

        foreach ($forbidden as $method) {
            \expect($contents)
                ->not->toContain($method);
        }
    }
});

\arch('app models do not use Eloquent model event methods', function (): void {
    $forbidden = [
        '::observe(',
        '::retrieved(',
        '::saving(',
        '::saved(',
        '::updating(',
        '::updated(',
        '::creating(',
        '::created(',
        '::replicating(',
        '::deleting(',
        '::deleted(',
    ];

    foreach (\arch_php_files(\base_path('app/Models')) as $file) {
        $contents = (string) \file_get_contents($file);

        foreach ($forbidden as $method) {
            \expect($contents)
                ->not->toContain($method);
        }
    }
});

\arch('app code does not use Dispatchable or DispatchesJobs traits', function (): void {
    $forbidden = [
        'use Dispatchable;',
        'use DispatchesJobs;',
        'use Illuminate\\Foundation\\Bus\\Dispatchable;',
        'use Illuminate\\Foundation\\Bus\\DispatchesJobs;',
        'use Illuminate\\Foundation\\Events\\Dispatchable;',
    ];

    foreach (\arch_php_files(\base_path('app')) as $file) {
        $contents = (string) \file_get_contents($file);

        foreach ($forbidden as $use) {
            \expect($contents)
                ->not->toContain($use);
        }
    }
});

\arch('app model reads use explicit getters and local scopes use static calls', function (): void {
    $scopes = [];
    foreach (\arch_php_files(\base_path('app/Models')) as $file) {
        $class = 'App\\' . \str_replace('/', '\\', \mb_substr($file, \mb_strlen(\app_path()) + 1, -4));
        foreach ((new ReflectionClass($class))->getMethods() as $method) {
            if (\str_starts_with($method->getName(), 'scope')) {
                $scopes[] = \lcfirst(\mb_substr($method->getName(), 5));
            }
        }
    }
    foreach (\arch_php_files(\base_path('app')) as $file) {
        $contents = (string) \file_get_contents($file);
        \expect($contents)->not->toMatch('/\\$(?:user|auth|model)->(?:id|email|password|locale|email_verified_at|remember_token|user)\\b(?!\\s*\\()/');
        foreach (\array_unique($scopes) as $scope) {
            \expect($contents)->not->toMatch('/\\$(?:query|builder)->' . \preg_quote($scope, '/') . '\\s*\\(/');
        }
    }
});

\arch('generated CRUD contracts require owned lookup and bounded pagination', function (): void {
    $stubs = \base_path('packages/thinkycz/laravel-core/stubs/');
    foreach (['show', 'update', 'destroy'] as $action) {
        $contents = (string) \file_get_contents($stubs . 'controller.' . $action . '.stub');
        \expect($contents)->toMatch('/scopeUserId\\(\\$builder, \\[\\$auth->getKey\\(\\)\\]\\);\\s*\\$model = \\$builder->firstOrFail\\(\\)/');
    }
    $contents = (string) \file_get_contents($stubs . 'controller.index.stub');
    \expect($contents)->toContain('public const int TAKE = 25;', 'public const int MAX_TAKE = 100;', '->take(static::MAX_TAKE)');
});
