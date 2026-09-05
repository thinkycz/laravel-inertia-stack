<?php

declare(strict_types=1);

require_once __DIR__ . '/Helpers.php';

$forbiddenFunctions = [
    'env(',
    'config(',
    'dd(',
    'var_dump(',
    'print_r(',
    'eval(',
    'exec(',
    'system(',
    'passthru(',
    'shell_exec(',
    'popen(',
    'proc_open(',
    'unserialize(',
    'extract(',
];

\arch('app code never calls forbidden debug/dangerous functions', function () use ($forbiddenFunctions): void {
    foreach (\arch_php_files(\base_path('app')) as $file) {
        $contents = (string) \file_get_contents($file);

        foreach ($forbiddenFunctions as $call) {
            \expect($contents)
                ->not->toMatch('/(?<!function )(?<![A-Za-z0-9_])' . \preg_quote($call, '/') . '/');
        }
    }
});

\arch('config files never call env directly', function (): void {
    foreach (\arch_php_files(\base_path('config')) ?: [] as $file) {
        $contents = (string) \file_get_contents($file);

        \expect($contents)
            ->not->toMatch('/(?<![A-Za-z0-9_])\\\\?env\\(/');
    }
});

\arch('app code never imports the raw config repository', function (): void {
    $forbidden = [
        'use Illuminate\\Contracts\\Config\\Repository;',
        'use Illuminate\\Config\\Repository;',
    ];

    foreach (\arch_php_files(\base_path('app')) as $file) {
        $contents = (string) \file_get_contents($file);

        foreach ($forbidden as $use) {
            \expect($contents)
                ->not->toContain($use);
        }
    }
});

\arch('app code never imports the raw Env facade', function (): void {
    $forbidden = [
        'use Illuminate\\Support\\Env;',
    ];

    foreach (\arch_php_files(\base_path('app')) as $file) {
        $contents = (string) \file_get_contents($file);

        foreach ($forbidden as $use) {
            \expect($contents)
                ->not->toContain($use);
        }
    }
});

\arch('app code never imports the raw translator contract', function (): void {
    $forbidden = [
        'use Illuminate\\Contracts\\Translation\\Translator;',
    ];

    foreach (\arch_php_files(\base_path('app')) as $file) {
        $contents = (string) \file_get_contents($file);

        foreach ($forbidden as $use) {
            \expect($contents)
                ->not->toContain($use);
        }
    }
});

\arch('app code never calls Application::environment, isLocal, isProduction, etc.', function (): void {
    $forbidden = [
        'app()->environment(',
        'app()->isLocal(',
        'app()->isProduction(',
        'app()->runningUnitTests(',
        'app()->hasDebugModeEnabled(',
        'app()->getLocale(',
        'app()->currentLocale(',
        'app()->getFallbackLocale(',
        'app()->isLocale(',
        'app()->setLocale(',
        'app()->setFallbackLocale(',
        'Application::environment(',
        'Application::isLocal(',
        'Application::isProduction(',
        'Application::runningUnitTests(',
        'Application::hasDebugModeEnabled(',
        'Application::getLocale(',
        'Application::currentLocale(',
        'Application::getFallbackLocale(',
        'Application::isLocale(',
        'Application::setLocale(',
        'Application::setFallbackLocale(',
    ];

    foreach (\arch_php_files(\base_path('app')) as $file) {
        $contents = (string) \file_get_contents($file);

        foreach ($forbidden as $call) {
            \expect($contents)
                ->not->toContain($call);
        }
    }
});

\arch('app code never calls AuthManager or PasswordBrokerManager methods directly', function (): void {
    $forbidden = [
        'Auth::getConfig(',
        'Auth::getDefaultDriver(',
        'Auth::shouldUse(',
        'Auth::setDefaultDriver(',
        'Password::getConfig(',
        'Password::getDefaultDriver(',
        'Password::setDefaultDriver(',
        'AuthManager::getConfig(',
        'AuthManager::getDefaultDriver(',
        'AuthManager::shouldUse(',
        'AuthManager::setDefaultDriver(',
        'PasswordBrokerManager::getConfig(',
        'PasswordBrokerManager::getDefaultDriver(',
        'PasswordBrokerManager::setDefaultDriver(',
    ];

    foreach (\arch_php_files(\base_path('app')) as $file) {
        $contents = (string) \file_get_contents($file);

        foreach ($forbidden as $call) {
            \expect($contents)
                ->not->toContain($call);
        }
    }
});

\arch('app code never calls ValidationException::withMessages directly', function (): void {
    $forbidden = [
        'ValidationException::withMessages(',
    ];

    foreach (\arch_php_files(\base_path('app')) as $file) {
        $contents = (string) \file_get_contents($file);

        foreach ($forbidden as $call) {
            \expect($contents)
                ->not->toContain($call);
        }
    }
});

\arch('app and route class references use imports instead of inline qualified names', function (): void {
    foreach ([...\arch_php_files(\base_path('app')), ...\arch_php_files(\base_path('routes'))] as $file) {
        $depth = 0;
        $importOrNamespace = false;
        foreach (\token_get_all((string) \file_get_contents($file)) as $token) {
            if (\is_string($token)) {
                if ($token === '{') {
                    ++$depth;
                } elseif ($token === '}') {
                    --$depth;
                } elseif ($token === ';') {
                    $importOrNamespace = false;
                }

                continue;
            }
            if ($depth === 0 && \in_array($token[0], [\T_NAMESPACE, \T_USE], true)) {
                $importOrNamespace = true;
            }
            if (!$importOrNamespace && \in_array($token[0], [\T_NAME_QUALIFIED, \T_NAME_FULLY_QUALIFIED], true) && !\defined(\ltrim($token[1], '\\'))) {
                $segments = \explode('\\', \ltrim($token[1], '\\'));
                $last = $segments[\count($segments) - 1];
                \expect(\preg_match('/^[A-Z]/', $last))->toBe(0, $file . ':' . $token[2] . ' should import ' . $token[1]);
            }
        }
    }
});

\arch('first party code does not suppress PHPStan findings', function (): void {
    foreach ([...\arch_php_files(\base_path('app')), ...\arch_php_files(\base_path('packages/thinkycz/laravel-core/src'))] as $file) {
        \expect((string) \file_get_contents($file))->not->toMatch('/@phpstan-ignore(?:-next-line|-line)?\\b/');
    }
});
