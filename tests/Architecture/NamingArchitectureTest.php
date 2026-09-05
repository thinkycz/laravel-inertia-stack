<?php

declare(strict_types=1);

require_once __DIR__ . '/Helpers.php';

\arch('controllers have the Controller suffix')
    ->expect('App\\Http\\Controllers')
    ->toHaveSuffix('Controller')
    ->ignoring('App\\Http\\Controllers\\Web\\Concerns');

\arch('resources have the Resource suffix', function (): void {
    \expect('App\\Http\\Resources')
        ->toHaveSuffix('Resource');
});

if (\is_dir(\dirname(__DIR__, 2) . '/app/Http/Validation')) {
    \arch('validity classes have the Validity suffix', function (): void {
        \expect('App\\Http\\Validation')
            ->toHaveSuffix('Validity');
    });

    \arch('classes in App\\Http\\Validation are cased correctly', function (): void {
        \expect('App\\Http\\Validation')
            ->toBeCasedCorrectly();
    });
}

\arch('enums end with Enum', function (): void {
    foreach (\arch_php_files(\base_path('app/Enums')) as $file) {
        $class = \pathinfo($file, \PATHINFO_FILENAME);

        \expect($class)->toEndWith('Enum');
    }
});

\arch('classes in App\\Enums are cased correctly (StudlyCase)', function (): void {
    \expect('App\\Enums')
        ->toBeCasedCorrectly();
});

\arch('classes in App\\Http\\Controllers are cased correctly', function (): void {
    \expect('App\\Http\\Controllers')
        ->toBeCasedCorrectly();
});

\arch('classes in App\\Models are cased correctly', function (): void {
    \expect('App\\Models')
        ->toBeCasedCorrectly();
});

\arch('classes in App\\Http\\Resources are cased correctly', function (): void {
    \expect('App\\Http\\Resources')
        ->toBeCasedCorrectly();
});
