<?php

namespace App\Providers\Filament;

use App\Filament\Auth\Login;
use App\Filament\Auth\MultiFactor\PasskeyAuthentication;
use App\Filament\Auth\SetupAccount;
use App\Filament\Pages\Dashboard;
use App\Http\Middleware\EnsureAccountSetup;
use App\Http\Middleware\EnsureOnboarding;
use App\Http\Middleware\SetLocale;
use App\Support\PlatformSettings;
use Filament\Auth\MultiFactor\App\AppAuthentication;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
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
            ->login(Login::class)
            ->registration(SetupAccount::class)
            ->profile()
            ->databaseNotifications()
            ->multiFactorAuthentication([
                AppAuthentication::make()
                    ->recoverable()
                    ->brandName(PlatformSettings::name()),
                // A passkey counts as the second factor; without this,
                // requiring 2FA would send a passkey user to set up an
                // authenticator app and never let them past it.
                PasskeyAuthentication::make(),
            ], isRequired: fn (): bool => PlatformSettings::requireMfa())
            ->colors([
                'primary' => PlatformSettings::filamentPrimary(),
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([
                Dashboard::class,
            ])
            // Discovery registers the watchlist widgets as Livewire components;
            // the dashboard page picks which of them it actually shows.
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
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
            ->renderHook(
                PanelsRenderHook::AUTH_LOGIN_FORM_AFTER,
                fn () => view('filament.hooks.passkey-login'),
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
                SetLocale::class,
                EnsureAccountSetup::class,
                EnsureOnboarding::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
