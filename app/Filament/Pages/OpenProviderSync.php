<?php

namespace App\Filament\Pages;

use App\Models\Domain;
use App\Registrars\OpenProvider\OpenProviderClient;
use App\Registrars\OpenProvider\OpenProviderSync as OpenProviderSyncService;
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
use Throwable;

class OpenProviderSync extends Page implements HasSchemas
{
    use InteractsWithSchemas;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedGlobeAlt;

    protected static ?string $navigationLabel = 'Openprovider';

    protected static \UnitEnum|string|null $navigationGroup = 'Catalog';

    protected static ?int $navigationSort = 41;

    protected static ?string $slug = 'openprovider';

    protected static ?string $title = 'Openprovider';

    protected string $view = 'filament.pages.openprovider-sync';

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
        return __('Openprovider');
    }

    public function getTitle(): string|Htmlable
    {
        return __('Openprovider');
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
                Section::make(__('API credentials'))
                    ->description(__('Your Openprovider control-panel login. Openprovider also requires this server’s IP address to be whitelisted under Account → Security.'))
                    ->schema([
                        TextInput::make('username')
                            ->label(__('Username'))
                            ->required(),
                        TextInput::make('password')
                            ->label(__('Password'))
                            ->password()
                            ->revealable()
                            ->helperText(fn (Get $get) => $get('has_password')
                                ? __('Set. Leave empty to keep the current password.')
                                : null),
                    ]),

                Section::make(__('Endpoint'))
                    ->description(__('Only change these when Openprovider moves the API. The defaults are the published ones.'))
                    ->collapsed()
                    ->schema([
                        TextInput::make('host')
                            ->label(__('API host'))
                            ->placeholder(OpenProviderClient::DEFAULT_HOST),
                        TextInput::make('version')
                            ->label(__('API version'))
                            ->placeholder(OpenProviderClient::DEFAULT_VERSION),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();
        PlatformSettings::set(PlatformSettings::OPENPROVIDER_USERNAME, $data['username'] ?? null);
        if (filled($data['password'] ?? null)) {
            PlatformSettings::set(PlatformSettings::OPENPROVIDER_PASSWORD, $data['password']);
        }
        PlatformSettings::set(PlatformSettings::OPENPROVIDER_HOST, $data['host'] ?? null);
        PlatformSettings::set(PlatformSettings::OPENPROVIDER_VERSION, $data['version'] ?? null);

        app(OpenProviderClient::class)->forgetToken();
        $this->form->fill($this->currentSettings());

        Notification::make()->title(__('Openprovider credentials saved'))->success()->send();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function lastReport(): ?array
    {
        $raw = PlatformSettings::get(PlatformSettings::OPENPROVIDER_LAST_SYNC_REPORT);
        if (! is_string($raw) || $raw === '') {
            return null;
        }
        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : null;
    }

    public function lastSyncAt(): ?string
    {
        $at = PlatformSettings::get(PlatformSettings::OPENPROVIDER_LAST_SYNC_AT);

        return filled($at) ? (string) $at : null;
    }

    public function lastError(): ?string
    {
        $error = PlatformSettings::get(PlatformSettings::OPENPROVIDER_LAST_ERROR);

        return filled($error) ? (string) $error : null;
    }

    public function unassignedCount(): int
    {
        return Domain::query()->whereNull('company_id')->count();
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
                        app(OpenProviderSyncService::class)->testConnection();
                        Notification::make()->title(__('Openprovider connection works'))->success()->send();
                    } catch (Throwable $e) {
                        Notification::make()
                            ->title(__('Openprovider connection failed'))
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),

            Action::make('sync')
                ->label(__('Sync now'))
                ->requiresConfirmation()
                ->modalHeading(__('Sync Openprovider now?'))
                ->modalDescription(__('Imports domains and refreshes the purchase price per extension. Customer links, notes and reminder settings are left alone.'))
                ->action(function (): void {
                    try {
                        $report = app(OpenProviderSyncService::class)->run();
                        Notification::make()
                            ->title(__('Openprovider sync finished'))
                            ->body(__(':created new, :updated updated, :unassigned without a customer.', [
                                'created' => $report->domainsCreated,
                                'updated' => $report->domainsUpdated,
                                'unassigned' => $report->domainsUnassigned,
                            ]))
                            ->success()
                            ->send();
                    } catch (Throwable $e) {
                        Notification::make()
                            ->title(__('Openprovider sync failed'))
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
            'username' => PlatformSettings::get(PlatformSettings::OPENPROVIDER_USERNAME),
            'password' => '',
            'has_password' => filled(PlatformSettings::get(PlatformSettings::OPENPROVIDER_PASSWORD)),
            'host' => PlatformSettings::get(PlatformSettings::OPENPROVIDER_HOST, OpenProviderClient::DEFAULT_HOST),
            'version' => PlatformSettings::get(PlatformSettings::OPENPROVIDER_VERSION, OpenProviderClient::DEFAULT_VERSION),
        ];
    }
}
