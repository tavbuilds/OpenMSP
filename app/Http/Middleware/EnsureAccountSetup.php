<?php

namespace App\Http\Middleware;

use App\Support\DemoAccount;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Empty install → registration. A demo user does not count as an administrator,
 * so login stays available and first-install registration stays open.
 */
class EnsureAccountSetup
{
    public function handle(Request $request, Closure $next): Response
    {
        if (
            $request->isMethod('GET')
            && $request->routeIs('filament.admin.*')
            && ! $request->routeIs('filament.admin.auth.register')
            && ! $request->routeIs('filament.admin.auth.login')
            && ! DemoAccount::hasRealOperator()
            && ! DemoAccount::exists()
        ) {
            return redirect()->route('filament.admin.auth.register');
        }

        return $next($request);
    }
}
