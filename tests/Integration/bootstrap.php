<?php

declare(strict_types=1);

require \dirname(__DIR__) . '/bootstrap.php';

$database = \getenv('STARTER_TEST_DATABASE');
$mysqlPort = \getenv('STARTER_TEST_MYSQL_PORT');
$redisPort = \getenv('STARTER_TEST_REDIS_PORT');
if (!\is_string($database) || \preg_match('/^starter_test_[a-f0-9]{12}$/', $database) !== 1 || !\ctype_digit((string) $mysqlPort) || !\ctype_digit((string) $redisPort)) {
    throw new RuntimeException('Use tests/Integration/run.mjs to provision disposable services.');
}
foreach ([
    'DB_CONNECTION' => 'mysql', 'DB_DATABASE' => $database,
    'DB_HOST' => '127.0.0.1', 'DB_PORT' => $mysqlPort, 'DB_USERNAME' => 'root',
    'DB_PASSWORD' => 'disposable-test-password', 'DB_SOCKET' => '',
    'REDIS_HOST' => '127.0.0.1', 'REDIS_PORT' => $redisPort,
    'REDIS_USERNAME' => '', 'REDIS_PASSWORD' => '', 'REDIS_PREFIX' => $database,
    'QUEUE_DRIVER' => 'redis', 'CACHE_DRIVER' => 'redis',
] as $key => $value) {
    \putenv($key . '=' . $value);
    $_ENV[$key] = $_SERVER[$key] = $value;
}
