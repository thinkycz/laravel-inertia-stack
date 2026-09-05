<?php

declare(strict_types=1);

require_once __DIR__ . '/Helpers.php';

\arch('enums are string-backed', function (): void {
    \expect('App\\Enums')
        ->toBeStringBackedEnums();
});

\arch('enums live directly under App\\Enums (no subdirectories)', function (): void {
    foreach (\arch_php_files(\base_path('app/Enums')) as $file) {
        \expect(\dirname($file))->toBe(\base_path('app/Enums'));
    }
});

\arch('enums expose a public static values() method', function (): void {
    foreach (\arch_php_files(\base_path('app/Enums')) as $file) {
        $contents = (string) \file_get_contents($file);

        \expect($contents)
            ->toMatch('/public static function values\\(\\): array/');
    }
});
