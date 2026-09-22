<?php

namespace App\Filament\Pages;

use App\Distributors\Pax8\Pax8Client;
use App\Distributors\Pax8\Pax8Sync as Pax8SyncService;
use App\Models\Product;
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
use Illuminate\Support\Collection;
use Throwable;

class Pax8Sync extends Page implements HasSchemas
{
    use InteractsWithSchemas;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowsRightLeft;

    protected static ?string $navigationLabel = 'Pax8';

    protected static \UnitEnum|string|null $navigationGroup = 'Catalog';

    protected static ?int $navigationSort = 40;

    protected static ?string $slug = 'pax8';

    protected static ?string $title = 'Pax8';

    protected string $view = 'filament.pages.pax8-sync';

    /** @var array<string, mixed> */
    public array $data = [];

    public string $catalogQuery = 'Microsoft 365 Business';

    public string $catalogVendor = 'Microsoft';

    /** @var list<array<string, mixed>> */
    public array $catalogHits = [];

    public ?string $catalogError = null;

    public static function getNavigationLabel(): string
    {
        return __('Pax8');
    }

    public function getTitle(): string|Htmlable
    {
        return __('Pax8');
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->canAdminister() ?? false;
    }

    public function mount(): void
    {
        $this->form->fill([
            'client_id' => PlatformSettings::get(PlatformSettings::PAX8_CLIENT_ID),
            'client_secret' => '',
            'has_secret' => filled(PlatformSettings::get(PlatformSettings::PAX8_CLIENT_SECRET)),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('API credentials'))
                    ->description(__('Create these in Pax8 → Integrations → Credentials. Name the credential OpenMSP. Paste Client ID and secret here — they stay on this server.'))
                    ->schema([
                        TextInput::make('client_id')
                            ->label(__('Client ID'))
                            ->required(),
                        TextInput::make('client_secret')
                            ->label(__('Client secret'))
                            ->password()
                            ->revealable()
                            ->helperText(fn (Get $get) => $get('has_secret') ? __('Set. Leave empty to keep the current secret.') : __('Shown only once in Pax8.')),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();
        PlatformSettings::set(PlatformSettings::PAX8_CLIENT_ID, $data['client_id'] ?? null);
        if (filled($data['client_secret'] ?? null)) {
            PlatformSettings::set(PlatformSettings::PAX8_CLIENT_SECRET, $data['client_secret']);
        }
        $this->form->fill([
            'client_id' => PlatformSettings::get(PlatformSettings::PAX8_CLIENT_ID),
            'client_secret' => '',
            'has_secret' => filled(PlatformSettings::get(PlatformSettings::PAX8_CLIENT_SECRET)),
        ]);
        Notification::make()->title(__('Pax8 credentials saved'))->success()->send();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function lastReport(): ?array
    {
        $raw = PlatformSettings::get(PlatformSettings::PAX8_LAST_SYNC_REPORT);
        if (! is_string($raw) || $raw === '') {
            return null;
        }
        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : null;
    }

    public function lastSyncAt(): ?string
    {
        $at = PlatformSettings::get(PlatformSettings::PAX8_LAST_SYNC_AT);

        return filled($at) ? (string) $at : null;
    }

    public function lastError(): ?string
    {
        $error = PlatformSettings::get(PlatformSettings::PAX8_LAST_ERROR);

        return filled($error) ? (string) $error : null;
    }

    public function searchCatalog(): void
    {
        $this->catalogError = null;
        $query = trim($this->catalogQuery);
        if ($query === '') {
            $this->catalogHits = [];

            return;
        }

        try {
            $this->catalogHits = app(Pax8Client::class)->searchProducts(
                $query,
                filled($this->catalogVendor) ? $this->catalogVendor : null,
            );
        } catch (Throwable $e) {
            $this->catalogHits = [];
            $this->catalogError = $e->getMessage();
        }
    }

    public function importCatalogProduct(string $productId): void
    {
        try {
            $product = app(Pax8SyncService::class)->importProduct($productId);
            Notification::make()
                ->title(__('Product imported'))
                ->body($product->name.' · € '.number_format((float) $product->default_cost_price, 2))
                ->success()
                ->send();
            $this->searchCatalog();
        } catch (Throwable $e) {
            Notification::make()->title(__('Import failed'))->body($e->getMessage())->danger()->send();
        }
    }

    /**
     * @return Collection<int, Product>
     */
    public function importedProducts(): Collection
    {
        return Product::query()
            ->with('vendor')
            ->where('source', Pax8Client::SOURCE)
            ->orderBy('name')
            ->get();
    }

    public function isImported(string $productId): bool
    {
        return Product::query()
            ->where('source', Pax8Client::SOURCE)
            ->where('source_id', $productId)
            ->exists();
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
                        app(Pax8Client::class)->token();
                        Notification::make()->title(__('Pax8 connection works'))->success()->send();
                    } catch (Throwable $e) {
                        Notification::make()->title(__('Pax8 connection failed'))->body($e->getMessage())->danger()->send();
                    }
                }),
            Action::make('sync')
                ->label(__('Sync now'))
                ->requiresConfirmation()
                ->modalHeading(__('Sync Pax8 now?'))
                ->modalDescription(__('Imports companies, refreshes imported catalog prices, and upserts subscriptions. Existing sale prices are kept.'))
                ->action(function (): void {
                    try {
                        $report = app(Pax8SyncService::class)->run();
                        Notification::make()
                            ->title(__('Pax8 sync finished'))
                            ->body(__(':products products, :contracts contracts, :companies companies.', [
                                'products' => $report->productsUpserted,
                                'contracts' => $report->contractsCreated + $report->contractsUpdated,
                                'companies' => $report->companiesCreated + $report->companiesMatched,
                            ]))
                            ->success()
                            ->send();
                    } catch (Throwable $e) {
                        PlatformSettings::set(PlatformSettings::PAX8_LAST_ERROR, $e->getMessage());
                        Notification::make()->title(__('Pax8 sync failed'))->body($e->getMessage())->danger()->send();
                    }
                }),
        ];
    }
}
