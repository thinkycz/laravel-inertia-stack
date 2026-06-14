<?php

declare(strict_types=1);

use Thinkycz\LaravelCore\Support\Typer;

\arch('app classes have all methods documented (replaces FunctionRequired)', function (): void {
    \expect('App')
        ->toHaveMethodsDocumented();
});

\arch('app classes have all properties documented (replaces PropertyRequired)', function (): void {
    \expect('App')
        ->toHavePropertiesDocumented();
});

\arch('core package classes have all methods documented', function (): void {
    \expect('Thinkycz\\LaravelCore')
        ->toHaveMethodsDocumented();
});

\arch('core package classes have all properties documented', function (): void {
    \expect('Thinkycz\\LaravelCore')
        ->toHavePropertiesDocumented();
});

\arch('app models do not declare @property/@method/@phpstan-method PHPDoc tags', function (): void {
    $banned = ['@property', '@method', '@phpstan-method'];
    $modelsPath = \dirname(__DIR__, 2) . '/app/Models';
    $files = \glob($modelsPath . '/*.php') ?: [];

    \expect($files)
        ->not->toBeEmpty('No model files found in app/Models; this guard has nothing to check.');

    foreach ($files as $file) {
        $contents = Typer::assertString(\file_get_contents($file));
        $found = \array_values(\array_filter(
            $banned,
            static fn(string $tag): bool => \str_contains($contents, $tag),
        ));

        \expect($found)
            ->toBe([], 'Model ' . \basename(Typer::assertString($file)) . ' must not declare ' . \implode(', ', $banned) . ' PHPDoc tags; use explicit getters or typed relation methods instead.');
    }
});
