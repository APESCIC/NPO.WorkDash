<?php

namespace App\Http\Middleware;

use App\Install\InstallState;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureNotInstalled
{
    public function __construct(private readonly InstallState $state) {}

    public function handle(Request $request, Closure $next): Response
    {
        abort_if($this->state->isInstalled(), 404);

        return $next($request);
    }
}
