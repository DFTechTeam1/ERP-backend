<?php

use App\Http\Middleware\BearerTokenMiddleware;
use App\Http\Middleware\CustomSignedRouteMiddleware;
use App\Http\Middleware\PermissionCheck;
use App\Http\Middleware\ScalarAuth;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        using: function () {
            Route::prefix('api')
                ->namespace('App\Http\Controllers\Api')
                ->group(base_path('routes/api.php'));

            Route::middleware(['web'])
                ->group(base_path('routes/web.php'));
        },
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'BearerToken' => BearerTokenMiddleware::class,
            'customSignedMiddleware' => CustomSignedRouteMiddleware::class,
            'permissionCheck' => PermissionCheck::class,
            'scalar.auth' => ScalarAuth::class
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        \Sentry\Laravel\Integration::handles($exceptions);
    })->create();
