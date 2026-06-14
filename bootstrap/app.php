<?php

declare(strict_types=1);

use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
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

        $middleware->web(append: [
            AuthShouldUseMiddleware::class,
            SetPreferredLanguageMiddleware::class,
            HandleInertiaRequests::class,
        ]);

        $middleware->api(append: [
            AuthShouldUseMiddleware::class,
            SetPreferredLanguageMiddleware::class,
            AddQueuedCookiesToResponse::class,
            SetRequestFormatMiddleware::class . ':json',
            ValidateAcceptHeaderMiddleware::class . ':application/vnd.api+json,application/json',
            ValidateContentTypeHeaderMiddleware::class . ':form,json',
        ]);
    })
    ->withSingletons([
        Illuminate\Contracts\Debug\ExceptionHandler::class => Thinkycz\LaravelCore\Exceptions\Handler::class,
    ])
    ->withSchedule(static function (Schedule $schedule): void {
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

        $exceptions->render(static function (Illuminate\Validation\ValidationException $exception, Request $request): mixed {
            if ($request->header('X-Inertia') !== 'true') {
                return null;
            }

            $component = $request->header('X-Inertia-Partial-Component') ?: match ($request->path()) {
                'verify-email' => 'auth/VerifyEmail',
                'forgot-password' => 'auth/ForgotPassword',
                'reset-password' => 'auth/ResetPassword',
                'register' => 'auth/Register',
                'settings/profile' => 'settings/Profile',
                'settings/password' => 'settings/Password',
                default => 'auth/Login',
            };

            $page = Inertia\Inertia::render($component, [
                'errors' => (object) $exception->errors(),
            ])->toResponse($request);

            return $page->setStatusCode(422);
        });
    })
    ->create();
