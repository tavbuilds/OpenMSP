<?php

namespace App\Providers;

use App\Models\Contract;
use App\Models\User;
use App\Observers\ContractObserver;
use App\Support\DemoAccount;
use App\Support\PlatformSettings;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Queue;
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

        // A price edited in the panel has to reach the subscription that
        // collects it, or we invoice one amount and debit another.
        Contract::observe(ContractObserver::class);

        // ...and again before every queued job. A worker boots once and runs
        // for hours, so without this it sends with the mail settings that
        // were stored when it started — which is how a working test mail and
        // a magic link that never arrives happen at the same time.
        Queue::before(fn () => PlatformSettings::refresh());

        // On a phone a wide table clips everything past the second column,
        // row actions included. Stacking turns each row into a labelled card.
        Table::configureUsing(fn (Table $table) => $table->stackedOnMobile());
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
