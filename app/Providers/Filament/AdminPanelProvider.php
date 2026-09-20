<?php

namespace App\Providers\Filament;

use App\Support\PlatformSettings;
use Filament\Auth\MultiFactor\App\AppAuthentication;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->brandName(fn (): string => PlatformSettings::name())
            ->brandLogo(fn (): ?string => PlatformSettings::logoUrl())
            ->brandLogoHeight('2rem')
            ->favicon(fn (): ?string => PlatformSettings::faviconUrl())
            ->sidebarCollapsibleOnDesktop()
            ->maxContentWidth('full')
            ->login(\App\Filament\Auth\Login::class)
            ->registration(\App\Filament\Auth\SetupAccount::class)
            ->profile()
            ->databaseNotifications()
            ->multiFactorAuthentication([
                AppAuthentication::make()
                    ->recoverable()
                    ->brandName(PlatformSettings::name()),
            ], isRequired: fn (): bool => PlatformSettings::requireMfa())
            ->colors([
                'primary' => PlatformSettings::filamentPrimary(),
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->widgets([
                \App\Filament\Widgets\PortfolioStats::class,
                \App\Filament\Widgets\FailedCollections::class,
                \App\Filament\Widgets\UpcomingEndpointExpiries::class,
                \App\Filament\Widgets\UpcomingNoticeDeadlines::class,
                \App\Filament\Widgets\UpcomingRenewals::class,
            ])
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                fn () => view('filament.hooks.responsive'),
            )
            ->renderHook(
                PanelsRenderHook::BODY_START,
                fn () => view('filament.hooks.demo-banner'),
            )
            ->renderHook(
                PanelsRenderHook::TOPBAR_END,
                fn () => view('filament.hooks.language-switcher'),
            )
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
                \App\Http\Middleware\SetLocale::class,
                \App\Http\Middleware\EnsureAccountSetup::class,
                \App\Http\Middleware\EnsureOnboarding::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
