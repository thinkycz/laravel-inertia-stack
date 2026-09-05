<?php

declare(strict_types=1);

require_once __DIR__ . '/Helpers.php';

\arch('resources end with the Resource suffix', function (): void {
    \expect('App\\Http\\Resources')
        ->toHaveSuffix('Resource');
});

\arch('resources extend Illuminate\\Http\\Resources\\JsonApi\\JsonApiResource', function (): void {
    \expect('App\\Http\\Resources')
        ->toExtend('Illuminate\\Http\\Resources\\JsonApi\\JsonApiResource');
});

\arch('resources declare toType and toAttributes', function (): void {
    foreach (\arch_php_files(\base_path('app/Http/Resources')) as $file) {
        $contents = (string) \file_get_contents($file);

        \expect($contents)
            ->toMatch('/public function toType\\(Request \\$request\\): string/')
            ->toMatch('/public function toAttributes\\(Request \\$request\\): array/');
    }
});

\arch('resources use Typer::assertInstance at the top of toAttributes', function (): void {
    foreach (\arch_php_files(\base_path('app/Http/Resources')) as $file) {
        $contents = (string) \file_get_contents($file);

        if (\preg_match('/public function toAttributes\\([^)]*\\): array\\s*\\{(.*?)\\n    \\}/s', $contents, $matches) === 1) {
            \expect($matches[1])
                ->toMatch('/Typer::assertInstance\\(\\$this->resource,\\s*\\w+::class\\)/');
        }
    }
});
