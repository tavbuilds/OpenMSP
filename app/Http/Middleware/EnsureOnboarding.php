<?php

namespace App\Http\Middleware;

use App\Filament\Pages\Onboarding;
use App\Models\User;
use App\Support\PlatformSettings;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * After the first admin exists, send operators through /admin/setup until
 * onboarding is marked complete. Login/register remain reachable.
 * Viewers/sales are not blocked; only admin/manager configure the platform.
 */
class EnsureOnboarding
{
    public function handle(Request $request, Closure $next): Response
    {
        if (
            ! $request->isMethod('GET')
            || ! $request->routeIs('filament.admin.*')
            || $request->routeIs('filament.admin.auth.*')
            || $request->routeIs('filament.admin.pages.setup')
            || $request->is('admin/setup')
            || $request->is('admin/setup/*')
            || ! User::query()->exists()
            || PlatformSettings::onboardingCompleted()
        ) {
            return $next($request);
        }

        $user = $request->user();
        if (! $user instanceof User || ! $user->canAdminister()) {
            return $next($request);
        }

        return redirect()->to(Onboarding::getUrl());
    }
}
