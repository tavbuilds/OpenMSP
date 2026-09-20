<?php

namespace App\Filament\Pages;

use App\Support\PlatformSettings;
use App\Support\UserAdministration;
use App\Support\Breakpoints;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class ManageSettings extends Page implements HasSchemas
{
    use InteractsWithSchemas;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static ?string $navigationLabel = 'Settings';

    protected static \UnitEnum|string|null $navigationGroup = 'System';

    protected static ?int $navigationSort = 90;

    protected static ?string $slug = 'settings';

    protected static ?string $title = 'Settings';

    protected string $view = 'filament.pages.manage-settings';

    /** @var array<string, mixed> */
    public array $data = [];

    public static function canAccess(): bool
    {
        return auth()->user()?->canAdminister() ?? false;
    }

    public function mount(): void
    {
        $this->form->fill([
            'platform_name' => PlatformSettings::name(),
            'mail_mailer' => PlatformSettings::get(PlatformSettings::MAIL_MAILER, config('mail.default')),
            'mail_host' => PlatformSettings::get(PlatformSettings::MAIL_HOST, config('mail.mailers.smtp.host')),
            'mail_port' => PlatformSettings::get(PlatformSettings::MAIL_PORT, config('mail.mailers.smtp.port')),
            'mail_username' => PlatformSettings::get(PlatformSettings::MAIL_USERNAME, config('mail.mailers.smtp.username')),
            'mail_password' => '',
            'mail_scheme' => PlatformSettings::get(PlatformSettings::MAIL_SCHEME, config('mail.mailers.smtp.scheme')),
            'mail_from_address' => PlatformSettings::get(PlatformSettings::MAIL_FROM_ADDRESS, config('mail.from.address')),
            'mail_from_name' => PlatformSettings::get(PlatformSettings::MAIL_FROM_NAME, PlatformSettings::name()),
            'stripe_key' => PlatformSettings::get(PlatformSettings::STRIPE_KEY, config('services.stripe.key')),
            'stripe_secret' => '',
            'stripe_webhook_secret' => '',
            'require_mfa' => PlatformSettings::requireMfa(),
            'primary_color' => PlatformSettings::primaryColor(),
            'logo' => PlatformSettings::logoPath(),
            'favicon' => PlatformSettings::faviconPath(),
            'has_mail_password' => filled(PlatformSettings::get(PlatformSettings::MAIL_PASSWORD, config('mail.mailers.smtp.password'))),
            'has_stripe_secret' => filled(PlatformSettings::get(PlatformSettings::STRIPE_SECRET, config('services.stripe.secret'))),
            'has_stripe_webhook' => filled(PlatformSettings::get(PlatformSettings::STRIPE_WEBHOOK_SECRET, config('services.stripe.webhook_secret'))),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Branding')
                    ->description(__('Name, color, and logos in admin, login, portal, and email subjects.'))
                    ->columns(Breakpoints::TWO)
                    ->schema([
                        Hidden::make('has_mail_password'),
                        Hidden::make('has_stripe_secret'),
                        Hidden::make('has_stripe_webhook'),
                        TextInput::make('platform_name')
                            ->label(__('Platform name'))
                            ->required()
                            ->maxLength(80),
                        ColorPicker::make('primary_color')
                            ->label(__('Primary color'))
                            ->default(PlatformSettings::DEFAULT_PRIMARY_COLOR),
                        FileUpload::make('logo')
                            ->label(__('Logo'))
                            ->image()
                            ->disk('public')
                            ->directory('branding')
                            ->visibility('public')
                            ->maxSize(2048)
                            ->helperText(__('PNG, SVG, or JPG. Empty = name only.')),
                        FileUpload::make('favicon')
                            ->label(__('Favicon'))
                            ->image()
                            ->disk('public')
                            ->directory('branding')
                            ->visibility('public')
                            ->maxSize(512),
                        Toggle::make('require_mfa')
                            ->label(__('Require 2FA for all administrators'))
                            ->helperText(__('Authenticator app (TOTP). Sanctum API tokens still work without 2FA.'))
                            ->columnSpanFull(),
                    ]),
                Section::make('Email')
                    ->description(__('Leave blank to fall back to environment variables (MAIL_*).'))
                    ->columns(Breakpoints::TWO)
                    ->schema([
                        Select::make('mail_mailer')
                            ->label(__('Mailer'))
                            ->options([
                                'log' => 'Log (local / test)',
                                'smtp' => 'SMTP',
                            ])
                            ->live(),
                        TextInput::make('mail_from_address')->label(__('From address'))->email(),
                        TextInput::make('mail_from_name')->label(__('From name')),
                        TextInput::make('mail_host')->label(__('SMTP host'))->visible(fn (Get $get) => $get('mail_mailer') === 'smtp'),
                        TextInput::make('mail_port')->label(__('SMTP port'))->numeric()->visible(fn (Get $get) => $get('mail_mailer') === 'smtp'),
                        TextInput::make('mail_username')->label(__('SMTP username'))->visible(fn (Get $get) => $get('mail_mailer') === 'smtp'),
                        TextInput::make('mail_password')
                            ->label(__('SMTP password'))
                            ->password()
                            ->revealable()
                            ->helperText(fn (Get $get) => $get('has_mail_password') ? 'Set. Leave empty to keep it.' : 'Optional.')
                            ->visible(fn (Get $get) => $get('mail_mailer') === 'smtp'),
                        TextInput::make('mail_scheme')
                            ->label(__('MAIL_SCHEME'))
                            ->placeholder(__('empty = STARTTLS (587); smtps = 465'))
                            ->visible(fn (Get $get) => $get('mail_mailer') === 'smtp'),
                    ]),
                Section::make('Stripe')
                    ->description(__('Customer portal iDEAL → SEPA. Empty secrets keep using env values.'))
                    ->columns(Breakpoints::TWO)
                    ->collapsed()
                    ->schema([
                        TextInput::make('stripe_key')->label(__('Publishable key'))->columnSpanFull(),
                        TextInput::make('stripe_secret')
                            ->label(__('Secret key'))
                            ->password()
                            ->revealable()
                            ->helperText(fn (Get $get) => $get('has_stripe_secret') ? 'Set. Leave empty to keep it.' : null),
                        TextInput::make('stripe_webhook_secret')
                            ->label(__('Webhook secret'))
                            ->password()
                            ->revealable()
                            ->helperText(fn (Get $get) => $get('has_stripe_webhook') ? 'Set. Leave empty to keep it.' : null),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();

        PlatformSettings::set(PlatformSettings::NAME, $data['platform_name'] ?? null);
        PlatformSettings::set(PlatformSettings::PRIMARY_COLOR, $data['primary_color'] ?? null);
        PlatformSettings::set(PlatformSettings::LOGO, is_array($data['logo'] ?? null) ? (reset($data['logo']) ?: null) : ($data['logo'] ?? null));
        PlatformSettings::set(PlatformSettings::FAVICON, is_array($data['favicon'] ?? null) ? (reset($data['favicon']) ?: null) : ($data['favicon'] ?? null));
        PlatformSettings::set(PlatformSettings::REQUIRE_MFA, ! empty($data['require_mfa']));
        PlatformSettings::set(PlatformSettings::MAIL_MAILER, $data['mail_mailer'] ?? null);
        PlatformSettings::set(PlatformSettings::MAIL_FROM_ADDRESS, $data['mail_from_address'] ?? null);
        PlatformSettings::set(PlatformSettings::MAIL_FROM_NAME, $data['mail_from_name'] ?? null);
        PlatformSettings::set(PlatformSettings::MAIL_HOST, $data['mail_host'] ?? null);
        PlatformSettings::set(PlatformSettings::MAIL_PORT, $data['mail_port'] ?? null);
        PlatformSettings::set(PlatformSettings::MAIL_USERNAME, $data['mail_username'] ?? null);
        PlatformSettings::set(PlatformSettings::MAIL_SCHEME, $data['mail_scheme'] ?? null);
        if (filled($data['mail_password'] ?? null)) {
            PlatformSettings::set(PlatformSettings::MAIL_PASSWORD, $data['mail_password']);
        }
        PlatformSettings::set(PlatformSettings::STRIPE_KEY, $data['stripe_key'] ?? null);
        if (filled($data['stripe_secret'] ?? null)) {
            PlatformSettings::set(PlatformSettings::STRIPE_SECRET, $data['stripe_secret']);
        }
        if (filled($data['stripe_webhook_secret'] ?? null)) {
            PlatformSettings::set(PlatformSettings::STRIPE_WEBHOOK_SECRET, $data['stripe_webhook_secret']);
        }

        PlatformSettings::applyToConfig();

        Notification::make()->title(__('Settings saved'))->success()->send();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('save')
                ->label(__('Save'))
                ->action('save'),
        ];
    }

    public function clearMailSecretsAction(): Action
    {
        return Action::make('clearMailSecrets')
            ->label(__('Reset email to env'))
            ->color('gray')
            ->requiresConfirmation()
            ->action(function (): void {
                foreach ([
                    PlatformSettings::MAIL_MAILER,
                    PlatformSettings::MAIL_HOST,
                    PlatformSettings::MAIL_PORT,
                    PlatformSettings::MAIL_USERNAME,
                    PlatformSettings::MAIL_PASSWORD,
                    PlatformSettings::MAIL_SCHEME,
                    PlatformSettings::MAIL_FROM_ADDRESS,
                    PlatformSettings::MAIL_FROM_NAME,
                ] as $key) {
                    PlatformSettings::forget($key);
                }
                Notification::make()->title(__('Email settings reset to environment variables'))->success()->send();
                $this->mount();
            });
    }

    public function clearStripeAction(): Action
    {
        return Action::make('clearStripe')
            ->label(__('Reset Stripe to env'))
            ->color('gray')
            ->requiresConfirmation()
            ->action(function (): void {
                foreach ([
                    PlatformSettings::STRIPE_KEY,
                    PlatformSettings::STRIPE_SECRET,
                    PlatformSettings::STRIPE_WEBHOOK_SECRET,
                ] as $key) {
                    PlatformSettings::forget($key);
                }
                Notification::make()->title(__('Stripe settings reset to environment variables'))->success()->send();
                $this->mount();
            });
    }

    public function revokeAllTokensAction(): Action
    {
        return Action::make('revokeAllTokens')
            ->label(__('Revoke all API tokens'))
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading(__('Revoke every token for every user?'))
            ->modalDescription(__('Integrations (MCP, CI, agents) will need a new token.'))
            ->action(function (): void {
                $n = UserAdministration::revokeAllTokens();
                Notification::make()->title("{$n} token(s) revoked")->success()->send();
            });
    }

    public function restartOnboardingAction(): Action
    {
        return Action::make('restartOnboarding')
            ->label(__('Restart onboarding'))
            ->color('warning')
            ->requiresConfirmation()
            ->action(function (): void {
                PlatformSettings::resetOnboarding();
                $this->redirect(Onboarding::getUrl());
            });
    }
}
