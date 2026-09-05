<?php

declare(strict_types=1);

use App\Ai\Agents\ChatAgent;
use App\Models\User;
use Laravel\Ai\Concerns\HasConversations;
use Laravel\Ai\Concerns\RemembersConversations;

require_once __DIR__ . '/Helpers.php';

\arch('app models extend BaseModel or BaseUser', function (): void {
    foreach (\arch_php_files(\base_path('app/Models')) as $file) {
        $contents = (string) \file_get_contents($file);

        \expect($contents)
            ->toMatch('/extends\\s+(BaseModel|BaseUser)/');
    }
});

\arch('non-User app models have querySelect and scopeSearch', function (): void {
    $files = \arch_php_files(\base_path('app/Models'));
    \expect($files)->not->toBeEmpty();

    foreach ($files as $file) {
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

\arch('chat and user keep the official SDK conversation traits', function (): void {
    \expect(\class_uses_recursive(ChatAgent::class))->toContain(RemembersConversations::class);
    \expect(\class_uses_recursive(User::class))->toContain(HasConversations::class);
});
