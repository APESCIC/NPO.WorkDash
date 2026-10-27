<?php

use App\Http\Middleware\EnsureNotInstalled;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\PrepareInstaller;
use App\Http\Middleware\RequireSuperAdminTwoFactor;
use App\Install\ReleaseLayout;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;

$app = Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Cloudron terminates TLS at its nginx proxy; only that proxy can reach the app container.
        $middleware->trustProxies(at: '*');

        $middleware->prepend(PrepareInstaller::class);

        // laravel_maintenance is read before cookies are decrypted (the updater's bypass cookie).
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state', 'laravel_maintenance']);

        $middleware->web(append: [
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
            RequireSuperAdminTwoFactor::class,
        ]);

        $middleware->alias([
            'not-installed' => EnsureNotInstalled::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();

ReleaseLayout::apply($app);

return $app;
