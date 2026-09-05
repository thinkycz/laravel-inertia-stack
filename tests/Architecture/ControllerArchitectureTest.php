<?php

declare(strict_types=1);

require_once __DIR__ . '/Helpers.php';

use Illuminate\Http\Request;

\arch('controllers end with the Controller suffix')
    ->expect('App\\Http\\Controllers')
    ->toHaveSuffix('Controller')
    ->ignoring('App\\Http\\Controllers\\Web\\Concerns');

\arch('api controllers extend AutomaticController', function (): void {
    \expect('App\\Http\\Controllers\\Api')
        ->toExtend('Thinkycz\\LaravelCore\\Routing\\AutomaticController');
});

\arch('api controllers do not define a constructor', function (): void {
    \expect('App\\Http\\Controllers\\Api')
        ->not->toHaveConstructor();
});

\arch('api controllers are invokable', function (): void {
    \expect('App\\Http\\Controllers\\Api')
        ->toBeInvokable();
});

\arch('api controllers have __invoke(ApiFormRequest $request): SymfonyResponse', function (): void {
    foreach (\arch_php_files(\base_path('app/Http/Controllers/Api')) as $file) {
        $contents = (string) \file_get_contents($file);

        \expect($contents)
            ->toMatch('/public function __invoke\\(\\s*ApiFormRequest\\s+\\$request\\s*\\):\\s*SymfonyResponse/');
    }
});

\arch('api controllers do not live outside the App\\Http\\Controllers\\Api namespace', function (): void {
    foreach (\arch_php_files(\base_path('app/Http/Controllers/Api')) as $file) {
        $contents = (string) \file_get_contents($file);

        \expect($contents)
            ->toContain('namespace App\\Http\\Controllers\\Api\\')
            ->toContain('extends AutomaticController');
    }
});

\arch('no app/Http/Requests/ directory exists', function (): void {
    \expect(\is_dir(\base_path('app/Http/Requests')))->toBeFalse();
});

\arch('no app/Http/Controllers/Admin/ directory exists', function (): void {
    \expect(\is_dir(\base_path('app/Http/Controllers/Admin')))->toBeFalse();
});

\arch('web index controllers declare a TAKE constant', function (): void {
    $files = \array_filter(\arch_php_files(\base_path('app/Http/Controllers/Web')), static fn(string $file): bool => \str_ends_with($file, 'IndexController.php'));

    // Ensure the test always registers at least one assertion so it is never
    // flagged as risky when no index controllers exist yet.
    \expect(true)->toBeTrue();

    foreach ($files as $file) {
        $contents = (string) \file_get_contents($file);

        \expect($contents)->toMatch('/public const int TAKE/');
    }
});

\arch('web controllers resolve services in method bodies', function (): void {
    foreach (\arch_php_files(\base_path('app/Http/Controllers/Web')) as $file) {
        if (\str_contains($file, '/Concerns/')) {
            continue;
        }
        $class = 'App\\' . \str_replace('/', '\\', \mb_substr($file, \mb_strlen(\app_path()) + 1, -4));
        $reflection = new ReflectionClass($class);
        \expect($reflection->getConstructor())->toBeNull();
        foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            foreach ($method->getParameters() as $parameter) {
                $type = $parameter->getType();
                $types = $type instanceof ReflectionUnionType || $type instanceof ReflectionIntersectionType ? $type->getTypes() : [$type];
                foreach ($types as $namedType) {
                    if ($namedType instanceof ReflectionNamedType && !$namedType->isBuiltin()) {
                        \expect(\is_a($namedType->getName(), Request::class, true))
                            ->toBeTrue($class . '::' . $method->getName() . ' must resolve service dependencies inside the action.');
                    }
                }
            }
        }
    }
});
