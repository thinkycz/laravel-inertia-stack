<?php

declare(strict_types=1);

use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\PreventCookieRequestForgery;
use App\Jobs\ReconcileAgentRunsJob;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Http\Request;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Thinkycz\LaravelCore\Http\Middleware\AuthShouldUseMiddleware;
use Thinkycz\LaravelCore\Http\Middleware\SetPreferredLanguageMiddleware;
use Thinkycz\LaravelCore\Http\Middleware\SetRequestFormatMiddleware;
use Thinkycz\LaravelCore\Http\Middleware\ValidateAcceptHeaderMiddleware;
use Thinkycz\LaravelCore\Http\Middleware\ValidateContentTypeHeaderMiddleware;
use Thinkycz\LaravelCore\Support\Config;
use Thinkycz\LaravelCore\Support\Env;

return Application::configure(basePath: \dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        health: '/up',
    )
    ->withMiddleware(static function (Middleware $middleware): void {
        $middleware->trustProxies(at: Env::inject()->parseNullableString('TRUSTED_PROXIES'));
        $middleware->redirectGuestsTo('/login');
        $middleware->redirectUsersTo('/dashboard');

        $middleware->replaceInGroup('web', PreventRequestForgery::class, PreventCookieRequestForgery::class);

        $middleware->web(append: [
            AuthShouldUseMiddleware::class,
            SetPreferredLanguageMiddleware::class,
            HandleInertiaRequests::class,
        ]);

        $middleware->api(prepend: [
            EncryptCookies::class,
            AddQueuedCookiesToResponse::class,
            StartSession::class,
            ShareErrorsFromSession::class,
            PreventCookieRequestForgery::class,
        ], append: [
            AuthShouldUseMiddleware::class,
            SetPreferredLanguageMiddleware::class,
            SetRequestFormatMiddleware::class . ':json',
            ValidateAcceptHeaderMiddleware::class . ':application/vnd.api+json,application/json',
            ValidateContentTypeHeaderMiddleware::class . ':form,json',
        ]);
    })
    ->withSingletons([
        Illuminate\Contracts\Debug\ExceptionHandler::class => Thinkycz\LaravelCore\Exceptions\Handler::class,
    ])
    ->withSchedule(static function (Schedule $schedule): void {
        $schedule->job(new ReconcileAgentRunsJob())->everyMinute();

        $config = Config::inject();

        $timezone = $config->assertString('app.schedule_timezone');

        foreach ($config->assertArray('auth.passwords') as $passwordBrokerName => $passwordBrokerConfig) {
            $schedule
                ->command("auth:clear-resets {$passwordBrokerName}")
                ->dailyAt('04:00')
                ->timezone($timezone)
                ->runInBackground();
        }

        $schedule
            ->command('cache:prune-stale-tags')
            ->hourly();
    })
    ->withExceptions(static function (Exceptions $exceptions): void {
        $exceptions->render(static function (ModelNotFoundException $exception, Request $request): never {
            throw new NotFoundHttpException(previous: $exception);
        });
    })
    ->create();
