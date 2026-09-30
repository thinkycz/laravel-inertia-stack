<?php

declare(strict_types=1);

\arch('app models extend BaseModel or BaseUser', function (): void {
    foreach (\glob(\base_path('app/Models/*.php')) as $file) {
        $contents = (string) \file_get_contents($file);

        \expect($contents)
            ->toMatch('/extends\\s+(BaseModel|BaseUser)/');
    }
});

\arch('non-User app models have querySelect and scopeSearch', function (): void {
    \expect(\is_file(\base_path('app/Models/User.php')))->toBeTrue();
    foreach (\glob(\base_path('app/Models/*.php')) as $file) {
        $contents = (string) \file_get_contents($file);

        if (\str_contains($contents, 'extends BaseUser')) {
            continue;
        }

        \expect($contents)
            ->toMatch('/public static function querySelect\\(/')
            ->toMatch('/public static function scopeSearch\\(/');
    }
});

\arch('non-Category app models have a casts() method', function (): void {
    foreach (\glob(\base_path('app/Models/*.php')) as $file) {
        $contents = (string) \file_get_contents($file);

        if (\str_contains($contents, 'class Category')) {
            continue;
        }

        \expect($contents)
            ->toMatch('/protected function casts\\(\\): array/');
    }
});
