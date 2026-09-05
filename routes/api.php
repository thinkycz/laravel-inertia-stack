<?php

declare(strict_types=1);

use App\Http\Controllers\Api\Auth\CsrfShowController;
use App\Http\Controllers\Api\Auth\LoginController;
use App\Http\Controllers\Api\Auth\LogoutController;
use App\Http\Controllers\Api\Auth\RegisterController;
use App\Http\Controllers\Api\EmailVerification\EmailVerificationResendController;
use App\Http\Controllers\Api\EmailVerification\EmailVerificationVerifyController;
use App\Http\Controllers\Api\Me\MeDestroyController;
use App\Http\Controllers\Api\Me\MeShowController;
use App\Http\Controllers\Api\Me\MeUpdateController;
use App\Http\Controllers\Api\Password\PasswordForgotController;
use App\Http\Controllers\Api\Password\PasswordResetController;
use App\Http\Controllers\Api\Password\PasswordUpdateController;
use Illuminate\Routing\Router;
use Thinkycz\LaravelCore\Support\Resolver;

Resolver::resolveRouteRegistrar()
    ->prefix('v1/me')
    ->group(static function (Router $router): void {
        $router->get('show', MeShowController::class);
        $router->post('update', MeUpdateController::class);
        $router->post('destroy', MeDestroyController::class);
    });

Resolver::resolveRouteRegistrar()
    ->prefix('v1/auth')
    ->group(static function (Router $router): void {
        $router->get('csrf', CsrfShowController::class);
        $router->post('login', LoginController::class)->name('login');
        $router->post('register', RegisterController::class);
        $router->post('logout', LogoutController::class);
    });

Resolver::resolveRouteRegistrar()
    ->prefix('v1/email_verification')
    ->group(static function (Router $router): void {
        $router->post('verify', EmailVerificationVerifyController::class);
        $router->post('resend', EmailVerificationResendController::class);
    });

Resolver::resolveRouteRegistrar()
    ->prefix('v1/password')
    ->group(static function (Router $router): void {
        $router->post('forgot', PasswordForgotController::class);
        $router->post('reset', PasswordResetController::class);
        $router->post('update', PasswordUpdateController::class);
    });
