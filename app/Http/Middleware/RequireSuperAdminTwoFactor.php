<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Laravel\Fortify\Features;
use Symfony\Component\HttpFoundation\Response;

/**
 * Send a Super Admin without two-factor authentication to the security page
 * until they set it up.
 */
class RequireSuperAdminTwoFactor
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (
            $user === null
            || $user->is_super_admin !== true
            || $user->hasEnabledTwoFactorAuthentication()
            || ! Features::canManageTwoFactorAuthentication()
            || $request->routeIs('security.edit', 'password.confirm*', 'two-factor.*', 'logout', 'verification.*')
        ) {
            return $next($request);
        }

        return redirect()->route('security.edit');
    }
}
