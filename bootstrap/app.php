<?php

use App\Http\Middleware\AllowIframe;
use App\Http\Middleware\AuthenticateWithAccessToken;
use App\Http\Middleware\AuthenticateWithAccessTokenOrSanctum;
use App\Http\Middleware\AuthenticateWithMcpToken;
use App\Http\Middleware\BearerTokenMiddleware;
use App\Http\Middleware\CustomSignedRouteMiddleware;
use App\Http\Middleware\InternalServiceMiddleware;
use App\Http\Middleware\LogMcpAccess;
use App\Http\Middleware\PartnerMiddleware;
use App\Http\Middleware\PermissionCheck;
use App\Http\Middleware\ScalarAuth;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Route;
use Sentry\Laravel\Integration;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        using: function () {
            Route::get('/up', fn () => response('OK'));

            Route::prefix('api')
                ->namespace('App\Http\Controllers\Api')
                ->group(base_path('routes/api.php'));

            Route::middleware(['web'])
                ->group(base_path('routes/web.php'));
        },
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Behind the docker edge nginx (and the host nginx terminating TLS), trust
        // the forwarded headers so the app sees the real client IP and the https
        // scheme. Without this, $request->isSecure() is false and signed-route
        // validation runs over http while generation forces https — a mismatch.
        $middleware->trustProxies(at: '*');

        $middleware->validateCsrfTokens(except: [
            '/oauth/token',
            '/oauth/register',
        ]);

        $middleware->alias([
            'jwt.auth' => AuthenticateWithAccessToken::class,
            'auth.session' => AuthenticateWithAccessTokenOrSanctum::class,
            'mcp.auth' => AuthenticateWithMcpToken::class,
            'mcp.log' => LogMcpAccess::class,
            'internal.service' => InternalServiceMiddleware::class,
            'allow-iframe' => AllowIframe::class,
            'partner' => PartnerMiddleware::class,
            'BearerToken' => BearerTokenMiddleware::class,
            'customSignedMiddleware' => CustomSignedRouteMiddleware::class,
            'permissionCheck' => PermissionCheck::class,
            'scalar.auth' => ScalarAuth::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        Integration::handles($exceptions);
    })->create();
