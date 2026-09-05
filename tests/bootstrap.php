<?php

declare(strict_types=1);

require \dirname(__DIR__) . '/vendor/autoload.php';

$testCacheDirectory = \sys_get_temp_dir() . '/starter-php-cache-' . \bin2hex(\random_bytes(8));
\mkdir($testCacheDirectory, 0o700);
\register_shutdown_function(static function () use ($testCacheDirectory): void {
    foreach (['config', 'routes', 'events', 'services', 'packages'] as $cache) {
        $file = $testCacheDirectory . '/' . $cache . '.php';
        if (\is_file($file)) {
            \unlink($file);
        }
    }
    \rmdir($testCacheDirectory);
});

// Set before Laravel can load cached configuration or a developer environment.
foreach ([
    'APP_ENV' => 'testing',
    'APP_CONFIG_CACHE' => $testCacheDirectory . '/config.php',
    'APP_ROUTES_CACHE' => $testCacheDirectory . '/routes.php',
    'APP_EVENTS_CACHE' => $testCacheDirectory . '/events.php',
    'APP_SERVICES_CACHE' => $testCacheDirectory . '/services.php',
    'APP_PACKAGES_CACHE' => $testCacheDirectory . '/packages.php',
    'DB_CONNECTION' => 'sqlite',
    'DB_DATABASE' => ':memory:',
    'CACHE_DRIVER' => 'array',
    'QUEUE_DRIVER' => 'sync',
    'SESSION_DRIVER' => 'array',
    'MAIL_MAILER' => 'array',
] as $key => $value) {
    \putenv($key . '=' . $value);
    $_ENV[$key] = $_SERVER[$key] = $value;
}
