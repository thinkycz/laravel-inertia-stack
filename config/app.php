<?php

declare(strict_types=1);

use Thinkycz\LaravelCore\Support\Env;
use Thinkycz\LaravelCore\Support\Typer;

$env = Env::inject();

return [
    /*
    |--------------------------------------------------------------------------
    | Application Name
    |--------------------------------------------------------------------------
    |
    | This value is the name of your application, which will be used when the
    | framework needs to place the application's name in a notification or
    | other UI elements where an application name needs to be displayed.
    |
    */

    'name' => $env->mustParseString('APP_NAME'),

    /*
    |--------------------------------------------------------------------------
    | Application Environment
    |--------------------------------------------------------------------------
    |
    | This value determines the "environment" your application is currently
    | running in. This may determine how you prefer to configure various
    | services the application utilizes. Set this in your ".env" file.
    |
    */

    'env' => Typer::assertIn($env->appEnv(), ['testing', 'local', 'development', 'staging', 'production']),

    /*
    |--------------------------------------------------------------------------
    | Application Debug Mode
    |--------------------------------------------------------------------------
    |
    | When your application is in debug mode, detailed error messages with
    | stack traces will be shown on every error that occurs within your
    | application. If disabled, a simple generic error page is shown.
    |
    */

    'debug' => $env->appEnvIs(['local', 'testing']),

    /*
    |--------------------------------------------------------------------------
    | Application URL
    |--------------------------------------------------------------------------
    |
    | This URL is used by the console to properly generate URLs when using
    | the Artisan command line tool. You should set this to the root of
    | the application so that it's available within Artisan commands.
    |
    */

    'url' => $env->mustParseString('APP_URL'),

    /*
    |--------------------------------------------------------------------------
    | Application Timezone
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default timezone for your application, which
    | will be used by the PHP date and date-time functions. The timezone
    | is set to "UTC" by default as it is suitable for most use cases.
    |
    */

    'timezone' => 'UTC',

    /*
    |--------------------------------------------------------------------------
    | Scheduler Timezone
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default timezone for your scheduler, which
    | will be used by CRON to run tasks in specific time in specific timezone.
    |
    */

    'schedule_timezone' => 'Europe/Prague',

    /*
    |--------------------------------------------------------------------------
    | Application Locale Configuration
    |--------------------------------------------------------------------------
    |
    | The application locale determines the default locale that will be used
    | by Laravel's translation / localization methods. This option can be
    | set to any locale for which you plan to have translation strings.
    |
    */

    'locale' => 'cs',

    'locales' => ['en', 'cs', 'sk'],

    'fallback_locale' => 'en',

    'faker_locale' => 'en_US',

    /*
    |--------------------------------------------------------------------------
    | Encryption Key
    |--------------------------------------------------------------------------
    |
    | This key is utilized by Laravel's encryption services and should be set
    | to a random, 32 character string to ensure that all encrypted values
    | are secure. You should do this prior to deploying the application.
    |
    */

    'cipher' => 'AES-256-CBC',

    'key' => $env->parseNullableString('APP_KEY'),

    'previous_keys' => [
        ...\array_filter(
            \explode(',', $env->parseNullableString('APP_PREVIOUS_KEYS') ?? ''),
            static fn(mixed $value): bool => $value !== '',
            \ARRAY_FILTER_USE_BOTH,
        ),
    ],

    /*
    |--------------------------------------------------------------------------
    | Maintenance Mode Driver
    |--------------------------------------------------------------------------
    |
    | These configuration options determine the driver used to determine and
    | manage Laravel's "maintenance mode" status. The "cache" driver will
    | allow maintenance mode to be controlled across multiple machines.
    |
    | Supported drivers: "file", "cache"
    |
    */

    'maintenance' => [
        'driver' => $env->parseNullableString('APP_MAINTENANCE_DRIVER') ?? 'file',
        'store' => $env->parseNullableString('APP_MAINTENANCE_STORE') ?? 'database',
    ],

    /*
    |--------------------------------------------------------------------------
    | End-to-end testing flags
    |--------------------------------------------------------------------------
    |
    | Toggles consumed by the application during end-to-end (Playwright)
    | runs. Reading through Config::inject() rather than Env::inject() keeps
    | the values stable across `php artisan config:cache` and the
    | configuration:clear/migrate:fresh sequence the e2e harness runs.
    |
    */

    'e2e' => [
        'disable_throttle' => $env->appEnvIs(['testing']) && ($env->parseNullableBool('E2E_DISABLE_THROTTLE') ?? false),
    ],
];
