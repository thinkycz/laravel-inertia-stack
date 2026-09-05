<?php

declare(strict_types=1);

require_once __DIR__ . '/Helpers.php';

\arch('app models extend BaseModel or BaseUser', function (): void {
    foreach (\arch_php_files(\base_path('app/Models')) as $file) {
        $contents = (string) \file_get_contents($file);

        \expect($contents)
            ->toMatch('/extends\\s+(BaseModel|BaseUser)/');
    }
});

\arch('non-User app models have querySelect and scopeSearch', function (): void {
    foreach (\arch_php_files(\base_path('app/Models')) as $file) {
        $contents = (string) \file_get_contents($file);

        if (\str_contains($contents, 'extends BaseUser')) {
            continue;
        }

        \expect($contents)
            ->toMatch('/public static function querySelect\\(/')
            ->toMatch('/public static function scopeSearch\\(/');
    }
});

\arch('app models have a casts() method', function (): void {
    foreach (\arch_php_files(\base_path('app/Models')) as $file) {
        $contents = (string) \file_get_contents($file);

        \expect($contents)
            ->toMatch('/protected function casts\\(\\): array/');
    }
});
