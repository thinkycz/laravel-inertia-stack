<?php

declare(strict_types=1);

use Illuminate\Http\Request;

$path = \parse_url($_SERVER['REQUEST_URI'], \PHP_URL_PATH);
$public = \realpath(\dirname(__DIR__, 3) . '/public');
$file = \is_string($path) ? \realpath($public . $path) : false;
if ($file !== false && \str_starts_with($file, $public . \DIRECTORY_SEPARATOR) && \is_file($file)) {
    return false;
}

$app = require __DIR__ . '/bootstrap.php';
$app->handleRequest(Request::capture());
