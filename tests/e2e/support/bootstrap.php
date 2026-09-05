<?php

declare(strict_types=1);

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Vite;
use Laravel\Ai\Ai;
use Tests\E2e\Support\BrowserTextGateway;

require \dirname(__DIR__, 3) . '/vendor/autoload.php';
require __DIR__ . '/BrowserTextGateway.php';

$directory = \getenv('E2E_DIRECTORY');
if (!\is_string($directory) || !\str_starts_with($directory, \sys_get_temp_dir() . '/laravel-inertia-e2e-') || !\is_dir($directory)) {
    throw new RuntimeException('Browser bootstrap requires a test-owned temporary directory.');
}
if (\getenv('APP_ENV') !== 'testing' || \getenv('DB_CONNECTION') !== 'sqlite' || \getenv('DB_DATABASE') !== $directory . '/database.sqlite') {
    throw new RuntimeException('Browser bootstrap refuses a non-isolated database.');
}

/** @var Application $app */
$app = require \dirname(__DIR__, 3) . '/bootstrap/app.php';
$app->useEnvironmentPath($directory);
$app->useStoragePath($directory . '/storage');
$app->booted(static function () use ($directory): void {
    Vite::useHotFile($directory . '/hot');
    Http::preventStrayRequests();
    Mail::fake();
    Notification::fake();
    Ai::textProvider()->useTextGateway((new BrowserTextGateway(static function (string $prompt): string {
        if ($prompt === '[e2e:failure]') {
            throw new RuntimeException('Synthetic provider failure; must never reach the browser.');
        }
        if (\str_starts_with($prompt, '[e2e:slow]')) {
            \usleep(4_000_000);
        }

        return 'Test assistant response: ' . $prompt;
    }))->preventStrayPrompts());
});

return $app;
