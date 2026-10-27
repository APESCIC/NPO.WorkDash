<?php

namespace App\Http\Middleware;

use App\Install\InstallerService;
use App\Install\InstallState;
use Closure;
use Illuminate\Cache\RateLimiter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Facade;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Until the app is installed: create .env with an APP_KEY on the first
 * request, keep sessions and cache off the (not yet configured) database, and
 * send every page to the install wizard.
 */
class PrepareInstaller
{
    public function __construct(
        private readonly InstallState $state,
        private readonly InstallerService $installer,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($this->state->isInstalled()) {
            return $next($request);
        }

        try {
            if ($this->installer->prepareEnvironment()) {
                return redirect($request->fullUrl());
            }
        } catch (RuntimeException $e) {
            return response($e->getMessage(), 500, ['Content-Type' => 'text/plain']);
        }

        $this->avoidDatabaseDrivers();

        if (! $request->is('install', 'install/*', 'up')) {
            return redirect('/install');
        }

        return $next($request);
    }

    /**
     * Sessions, cache and the queue cannot use the database before it is set up.
     */
    private function avoidDatabaseDrivers(): void
    {
        if (config('session.driver') === 'database') {
            config(['session.driver' => 'file']);
        }

        if (config('queue.default') === 'database') {
            config(['queue.default' => 'sync']);
        }

        if (config('cache.stores.'.config('cache.default').'.driver') === 'database') {
            config(['cache.default' => 'file', 'cache.limiter' => 'file']);

            // Service providers resolve the rate limiter at boot, bound to the database store.
            app()->forgetInstance(RateLimiter::class);
            Facade::clearResolvedInstance(RateLimiter::class);
        }
    }
}
