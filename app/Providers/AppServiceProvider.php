<?php

namespace App\Providers;

use App\Models\User;
use App\Support\DemoAccount;
use App\Support\PlatformSettings;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        PlatformSettings::applyToConfig();
        try {
            DemoAccount::ensureIfConfigured();
        } catch (\Throwable) {
            // Database may not be migrated yet (package:discover, first boot).
        }

        User::created(function (User $user): void {
            if (! $user->is_demo) {
                DemoAccount::purge();
            }
        });

        View::composer('portal.layouts.app', function ($view): void {
            $view->with([
                'platformName' => PlatformSettings::name(),
                'platformLogo' => PlatformSettings::logoUrl(),
                'platformFavicon' => PlatformSettings::faviconUrl(),
                'platformColor' => PlatformSettings::primaryColor(),
            ]);
        });
    }
}
