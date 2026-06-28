<?php

declare(strict_types=1);

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
    foreach (\glob(\base_path('app/Http/Controllers/Api/*/*.php')) as $file) {
        $contents = (string) \file_get_contents($file);

        \expect($contents)
            ->toMatch('/public function __invoke\\(\\s*ApiFormRequest\\s+\\$request\\s*\\):\\s*SymfonyResponse/');
    }
});

\arch('api controllers do not live outside the App\\Http\\Controllers\\Api namespace', function (): void {
    foreach (\glob(\base_path('app/Http/Controllers/Api/*/*.php')) as $file) {
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
    $files = \glob(\base_path('app/Http/Controllers/Web/**/*IndexController.php')) ?: [];

    // Ensure the test always registers at least one assertion so it is never
    // flagged as risky when no index controllers exist yet.
    \expect(true)->toBeTrue();

    foreach ($files as $file) {
        $contents = (string) \file_get_contents($file);

        \expect($contents)->toMatch('/public const int TAKE/');
    }
});
