<?php

declare(strict_types=1);

/**
 * Recursively iterate every .php file under a directory.
 *
 * @return iterable<string>
 */
function arch_php_files(string $dir): iterable
{
    $rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));

    foreach ($rii as $file) {
        /** @var SplFileInfo $file */
        if ($file->isFile() && $file->getExtension() === 'php') {
            yield $file->getPathname();
        }
    }
}

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
    foreach (\glob(\base_path('config/*.php')) ?: [] as $file) {
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
