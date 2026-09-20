<?php

namespace App\Filament\Pages;

use App\Support\PlatformSettings;
use App\Support\UserAdministration;
use BackedEnum;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Wizard;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class Onboarding extends Page implements HasSchemas
{
    use InteractsWithSchemas;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFlag;

    protected static ?string $navigationLabel = 'Setup';

    protected static ?string $slug = 'setup';

    protected static ?string $title = 'First-run setup';

    protected static ?int $navigationSort = 1;

    protected string $view = 'filament.pages.onboarding';

    /** @var array<string, mixed> */
    public array $data = [];

    public ?string $plainTextToken = null;

    public static function shouldRegisterNavigation(): bool
    {
        return ! PlatformSettings::onboardingCompleted();
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->canAdminister() ?? false;
    }

    public function mount(): void
    {
        $this->form->fill([
            'platform_name' => PlatformSettings::name(),
            'primary_color' => PlatformSettings::primaryColor(),
            'logo' => PlatformSettings::logoPath(),
            'favicon' => PlatformSettings::faviconPath(),
            'mail_mailer' => PlatformSettings::get(PlatformSettings::MAIL_MAILER, config('mail.default', 'log')),
            'mail_from_address' => PlatformSettings::get(PlatformSettings::MAIL_FROM_ADDRESS, config('mail.from.address')),
            'mail_from_name' => PlatformSettings::get(PlatformSettings::MAIL_FROM_NAME, PlatformSettings::name()),
            'mail_host' => PlatformSettings::get(PlatformSettings::MAIL_HOST),
            'mail_port' => PlatformSettings::get(PlatformSettings::MAIL_PORT, 587),
            'mail_username' => PlatformSettings::get(PlatformSettings::MAIL_USERNAME),
            'mail_password' => '',
            'stripe_key' => PlatformSettings::get(PlatformSettings::STRIPE_KEY),
            'stripe_secret' => '',
            'create_token' => true,
            'token_name' => 'agent',
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Wizard::make([
                    Step::make('Branding')
                        ->description(__('Name, color, and logo'))
                        ->schema([
                            TextInput::make('platform_name')
                                ->label(__('Platform name'))
                                ->required()
                                ->maxLength(80)
                                ->helperText(__('Shown in admin, the portal, and authenticator apps.')),
                            ColorPicker::make('primary_color')
                                ->label(__('Primary color'))
                                ->default(PlatformSettings::DEFAULT_PRIMARY_COLOR),
                            FileUpload::make('logo')
                                ->label(__('Logo'))
                                ->image()
                                ->disk('public')
                                ->directory('branding')
                                ->visibility('public')
                                ->maxSize(2048),
                            FileUpload::make('favicon')
                                ->label(__('Favicon'))
                                ->image()
                                ->disk('public')
                                ->directory('branding')
                                ->visibility('public')
                                ->maxSize(512),
                        ]),
                    Step::make('Email')
                        ->description(__('Optional — you can set this later'))
                        ->schema([
                            Select::make('mail_mailer')
                                ->label(__('Mailer'))
                                ->options([
                                    'log' => 'Log file (recommended until SMTP is ready)',
                                    'smtp' => 'SMTP',
                                ])
                                ->required()
                                ->live(),
                            TextInput::make('mail_from_address')->label(__('From address'))->email(),
                            TextInput::make('mail_from_name')->label(__('From name')),
                            TextInput::make('mail_host')->label(__('SMTP host'))->visible(fn (Get $get) => $get('mail_mailer') === 'smtp'),
                            TextInput::make('mail_port')->label(__('SMTP port'))->numeric()->visible(fn (Get $get) => $get('mail_mailer') === 'smtp'),
                            TextInput::make('mail_username')->label(__('SMTP username'))->visible(fn (Get $get) => $get('mail_mailer') === 'smtp'),
                            TextInput::make('mail_password')->label(__('SMTP password'))->password()->visible(fn (Get $get) => $get('mail_mailer') === 'smtp'),
                        ]),
                    Step::make('Stripe')
                        ->description(__('Optional — portal auto-collect'))
                        ->schema([
                            TextInput::make('stripe_key')->label(__('Publishable key (pk_…)')),
                            TextInput::make('stripe_secret')->label(__('Secret key (sk_…)'))->password(),
                        ]),
                    Step::make('API token')
                        ->description(__('For agents / MCP'))
                        ->schema([
                            Toggle::make('create_token')
                                ->label(__('Create a first API token'))
                                ->default(true)
                                ->live()
                                ->helperText(__('No tinker. You can create, revoke, or reset tokens later under API tokens.')),
                            TextInput::make('token_name')
                                ->label(__('Token name'))
                                ->default('agent')
                                ->visible(fn (Get $get) => (bool) $get('create_token')),
                        ]),
                ]),
            ])
            ->statePath('data');
    }

    public function complete(): void
    {
        $data = $this->form->getState();

        PlatformSettings::set(PlatformSettings::NAME, $data['platform_name'] ?? 'MSP Platform');
        PlatformSettings::set(PlatformSettings::PRIMARY_COLOR, $data['primary_color'] ?? null);
        PlatformSettings::set(PlatformSettings::LOGO, is_array($data['logo'] ?? null) ? (reset($data['logo']) ?: null) : ($data['logo'] ?? null));
        PlatformSettings::set(PlatformSettings::FAVICON, is_array($data['favicon'] ?? null) ? (reset($data['favicon']) ?: null) : ($data['favicon'] ?? null));
        PlatformSettings::set(PlatformSettings::MAIL_MAILER, $data['mail_mailer'] ?? 'log');
        PlatformSettings::set(PlatformSettings::MAIL_FROM_ADDRESS, $data['mail_from_address'] ?? null);
        PlatformSettings::set(PlatformSettings::MAIL_FROM_NAME, $data['mail_from_name'] ?? null);
        PlatformSettings::set(PlatformSettings::MAIL_HOST, $data['mail_host'] ?? null);
        PlatformSettings::set(PlatformSettings::MAIL_PORT, $data['mail_port'] ?? null);
        PlatformSettings::set(PlatformSettings::MAIL_USERNAME, $data['mail_username'] ?? null);
        if (filled($data['mail_password'] ?? null)) {
            PlatformSettings::set(PlatformSettings::MAIL_PASSWORD, $data['mail_password']);
        }
        PlatformSettings::set(PlatformSettings::STRIPE_KEY, $data['stripe_key'] ?? null);
        if (filled($data['stripe_secret'] ?? null)) {
            PlatformSettings::set(PlatformSettings::STRIPE_SECRET, $data['stripe_secret']);
        }

        if (! empty($data['create_token'])) {
            $this->plainTextToken = UserAdministration::createToken(
                auth()->user(),
                $data['token_name'] ?? 'agent',
            );
        }

        PlatformSettings::markOnboardingComplete();
        PlatformSettings::applyToConfig();

        Notification::make()
            ->title(__('Setup complete'))
            ->body('You can change or reset tokens, users, and SMTP/Stripe later under System.')
            ->success()
            ->send();

        if (filled($this->plainTextToken)) {
            return;
        }

        $this->redirect(url('/admin'));
    }

    public function skip(): void
    {
        if (! filled(PlatformSettings::get(PlatformSettings::NAME))) {
            PlatformSettings::set(PlatformSettings::NAME, 'MSP Platform');
        }
        PlatformSettings::markOnboardingComplete();
        $this->redirect(url('/admin'));
    }
}
