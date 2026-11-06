<?php

namespace App\Filament\Pages;

use App\Monitoring\UptimeKuma\UptimeKumaClient;
use App\Monitoring\UptimeKuma\UptimeKumaException;
use App\Monitoring\UptimeKuma\UptimeKumaWebhooks;
use App\Support\PlatformSettings;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Validation\ValidationException;
use Throwable;

class UptimeKuma extends Page implements HasSchemas
{
    use InteractsWithSchemas;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSignal;

    protected static ?string $navigationLabel = 'Uptime Kuma';

    protected static \UnitEnum|string|null $navigationGroup = 'Catalog';

    protected static ?int $navigationSort = 42;

    protected static ?string $slug = 'uptime-kuma';

    protected static ?string $title = 'Uptime Kuma';

    protected string $view = 'filament.pages.uptime-kuma';

    /** @var array<string, mixed> */
    public array $data = [];

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return is_string(static::$navigationGroup)
            ? __(static::$navigationGroup)
            : parent::getNavigationGroup();
    }

    public static function getNavigationLabel(): string
    {
        return __('Uptime Kuma');
    }

    public function getTitle(): string|Htmlable
    {
        return __('Uptime Kuma');
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->canAdminister() ?? false;
    }

    public function mount(): void
    {
        $this->form->fill($this->currentSettings());
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('API key'))
                    ->description(__('Reads certificate days from /metrics. It cannot add a webhook.'))
                    ->schema([
                        TextInput::make('base_url')
                            ->label(__('Base URL'))
                            ->placeholder('https://status.example.com'),
                        TextInput::make('api_key')
                            ->label(__('API key'))
                            ->password()
                            ->revealable()
                            ->helperText(fn (Get $get) => $get('has_api_key')
                                ? __('Set. Leave empty to keep the current API key.')
                                : __('From Uptime Kuma, Settings, Security, API Keys.')),
                    ]),
                Section::make(__('Kuma login'))
                    ->description(__('The dashboard username and password. OpenMSP uses them to add each endpoint webhook URL. Certificate and domain expiry notifications on the matched monitor are turned off.'))
                    ->schema([
                        TextInput::make('username')
                            ->label(__('Username')),
                        TextInput::make('password')
                            ->label(__('Password'))
                            ->password()
                            ->revealable()
                            ->helperText(fn (Get $get) => $get('has_password')
                                ? __('Set. Leave empty to keep the current password.')
                                : null),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();
        $baseUrl = $this->normaliseBaseUrl($data['base_url'] ?? null);

        if ($baseUrl === null) {
            PlatformSettings::forget(PlatformSettings::UPTIME_KUMA_BASE_URL);
        } else {
            PlatformSettings::set(PlatformSettings::UPTIME_KUMA_BASE_URL, $baseUrl);
        }

        if (filled($data['api_key'] ?? null)) {
            PlatformSettings::set(PlatformSettings::UPTIME_KUMA_API_KEY, $data['api_key']);
        }

        $username = trim((string) ($data['username'] ?? ''));
        if ($username === '') {
            PlatformSettings::forget(PlatformSettings::UPTIME_KUMA_USERNAME);
        } else {
            PlatformSettings::set(PlatformSettings::UPTIME_KUMA_USERNAME, $username);
        }

        if (filled($data['password'] ?? null)) {
            PlatformSettings::set(PlatformSettings::UPTIME_KUMA_PASSWORD, $data['password']);
        }

        $this->form->fill($this->currentSettings());

        Notification::make()
            ->title(__('Uptime Kuma settings saved'))
            ->success()
            ->send();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('save')
                ->label(__('Save'))
                ->action('save'),
            Action::make('test')
                ->label(__('Test connection'))
                ->color('gray')
                ->action(function (): void {
                    try {
                        $result = app(UptimeKumaClient::class)->test();
                        Notification::make()
                            ->title(__('Uptime Kuma connection works'))
                            ->body(__(':count certificate monitors.', ['count' => $result['cert_monitors']]))
                            ->success()
                            ->send();
                    } catch (Throwable $e) {
                        Notification::make()
                            ->title(__('Uptime Kuma connection failed'))
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),
            Action::make('retryFailed')
                ->label(__('Submit failed domains again'))
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading(__('Submit the failed domains to Uptime Kuma again?'))
                ->modalDescription(__('OpenMSP sends only the domains whose last push to Uptime Kuma failed. Each domain is tried up to 3 times.'))
                ->action(function (): void {
                    try {
                        $report = app(UptimeKumaWebhooks::class)->push(failedOnly: true);
                    } catch (UptimeKumaException $e) {
                        Notification::make()
                            ->title(__('Could not add the webhook URLs'))
                            ->body($e->getMessage())
                            ->danger()
                            ->send();

                        return;
                    }

                    if ($report['endpoints'] === 0) {
                        Notification::make()
                            ->title(__('No domains are waiting for another try.'))
                            ->info()
                            ->send();

                        return;
                    }

                    $notification = Notification::make()
                        ->title(__('Webhook URLs sent to Uptime Kuma'))
                        ->body(UptimeKumaWebhooks::summary($report));
                    ($report['failed_count'] > 0 ? $notification->warning() : $notification->success())->send();
                }),
            Action::make('webhooks')
                ->label(__('Add webhook URLs'))
                ->requiresConfirmation()
                ->modalHeading(__('Add the endpoint webhook URLs to Uptime Kuma?'))
                ->modalDescription(__('Each endpoint gets a JSON webhook. When Uptime Kuma has no monitor for that URL or hostname, OpenMSP creates an HTTPS monitor and attaches the webhook. Other channels stay as they are. Certificate and domain expiry notifications are turned off.'))
                ->action(function (): void {
                    try {
                        $report = app(UptimeKumaWebhooks::class)->push();
                        $notification = Notification::make()
                            ->title(__('Webhook URLs sent to Uptime Kuma'))
                            ->body(UptimeKumaWebhooks::summary($report));
                        ($report['failed_count'] > 0 ? $notification->warning() : $notification->success())->send();
                    } catch (UptimeKumaException $e) {
                        Notification::make()
                            ->title(__('Could not add the webhook URLs'))
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function currentSettings(): array
    {
        return [
            'base_url' => PlatformSettings::get(PlatformSettings::UPTIME_KUMA_BASE_URL),
            'api_key' => '',
            'has_api_key' => filled(PlatformSettings::get(PlatformSettings::UPTIME_KUMA_API_KEY)),
            'username' => PlatformSettings::get(PlatformSettings::UPTIME_KUMA_USERNAME),
            'password' => '',
            'has_password' => filled(PlatformSettings::get(PlatformSettings::UPTIME_KUMA_PASSWORD)),
        ];
    }

    private function normaliseBaseUrl(mixed $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        if (str_ends_with($value, '/metrics')) {
            $value = substr($value, 0, -strlen('/metrics'));
        }

        $value = rtrim($value, '/');
        $parts = parse_url($value);
        $scheme = is_array($parts) ? ($parts['scheme'] ?? '') : '';

        if (! is_array($parts) || ! in_array($scheme, ['http', 'https'], true) || empty($parts['host'])) {
            throw ValidationException::withMessages([
                'data.base_url' => __('Enter the Uptime Kuma address, for example https://status.example.com.'),
            ]);
        }

        if (isset($parts['user']) || isset($parts['pass'])) {
            throw ValidationException::withMessages([
                'data.base_url' => __('Put the API key in the API key field, not in the URL.'),
            ]);
        }

        return $value;
    }
}
