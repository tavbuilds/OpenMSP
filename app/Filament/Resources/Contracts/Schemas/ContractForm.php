<?php

namespace App\Filament\Resources\Contracts\Schemas;

use App\Enums\BillingCycle;
use App\Enums\ContractStatus;
use App\Enums\ProductType;
use App\Models\Product;
use App\Support\Breakpoints;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class ContractForm
{
    /**
     * Flat form (edit page): the same fields grouped into sections.
     * The create page uses the groups below as wizard steps.
     */
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make(__('Customer & service'))->columns(Breakpoints::THREE)->schema(self::customerServiceComponents()),
                Section::make(__('Price & quantity'))->columns(Breakpoints::THREE)->schema(self::prijsQuantityComponents()),
                Section::make(__('Term & renewal'))->columns(Breakpoints::THREE)->schema(self::looptijdComponents()),
                Section::make(__('Auto-collect'))
                    ->description(__('Read-only. Customers enable this in the portal (iDEAL → SEPA).'))
                    ->columns(Breakpoints::TWO)
                    ->collapsed()
                    ->schema(self::incassoComponents()),
                Section::make(__('License keys & notes'))->columns(1)->collapsed()->schema(self::licentiesComponents()),
            ]);
    }

    /** @return array<int, \Filament\Schemas\Components\Component> */
    public static function customerServiceComponents(): array
    {
        return [
            Select::make('company_id')
                ->label(__('Company'))
                ->relationship('company', 'name')
                ->searchable()
                ->preload()
                ->required()
                ->createOptionForm([
                    TextInput::make('name')->label(__('Company name'))->required(),
                    TextInput::make('email')->email(),
                    TextInput::make('phone')->label(__('Phone')),
                    TextInput::make('city')->label(__('City')),
                ]),

            Select::make('vendor_id')
                ->label(__('Vendor'))
                ->relationship('vendor', 'name')
                ->searchable()
                ->preload()
                ->createOptionForm([
                    TextInput::make('name')->label(__('Name'))->required(),
                    TextInput::make('website'),
                ]),

            Select::make('product_id')
                ->label(__('Service / license (from catalog)'))
                ->relationship('product', 'name')
                ->searchable()
                ->preload()
                ->live()
                ->helperText(__('Pick an existing product to fill cost and sale automatically, or create a new product.'))
                ->afterStateUpdated(function (?string $state, Set $set) {
                    $product = $state ? Product::find($state) : null;
                    if (! $product) {
                        return;
                    }
                    $set('name', $product->name);
                    $set('type', $product->type?->value);
                    $set('vendor_id', $product->vendor_id);
                    $set('cost_price', $product->effective_cost_price);
                    $set('sale_price', $product->default_sale_price);
                    $set('currency', $product->currency);
                    $set('billing_cycle', $product->billing_cycle?->value);
                })
                ->createOptionForm([
                    TextInput::make('name')->label(__('Product name'))->required(),
                    Select::make('type')->label(__('Type'))->options(ProductType::class)->default('license')->required(),
                    TextInput::make('default_cost_price')->label(__('Cost price'))->numeric()->prefix('€')->default(0),
                    TextInput::make('default_sale_price')->label(__('Sale price'))->numeric()->prefix('€')->default(0),
                    Select::make('billing_cycle')->label(__('Billing'))->options(BillingCycle::class)->default('yearly')->required(),
                ]),

            TextInput::make('name')
                ->label(__('Service / package name'))
                ->required(),

            TextInput::make('reference')
                ->label(__('Reference / order number')),

            Select::make('type')
                ->label(__('Type'))
                ->options(ProductType::class)
                ->default('license')
                ->required(),
        ];
    }

    /** @return array<int, \Filament\Schemas\Components\Component> */
    public static function prijsQuantityComponents(): array
    {
        return [
            Select::make('purchase_bundle_id')
                ->label(__('Purchase bundle (shared cost)'))
                ->relationship('purchaseBundle', 'name')
                ->searchable()
                ->preload()
                ->live()
                ->columnSpanFull()
                ->helperText(__('Optional. Link to a purchased bundle (e.g. hosting) whose cost is split equally across active customer contracts. The per-unit cost is then ignored.'))
                ->createOptionForm([
                    TextInput::make('name')->label(__('Bundle name'))->required(),
                    Select::make('vendor_id')->label(__('Vendor'))->relationship('vendor', 'name')->searchable()->preload(),
                    TextInput::make('total_cost')->label(__('Total cost'))->numeric()->prefix('€')->default(0)->required(),
                    Select::make('billing_cycle')->label(__('Billing cycle'))->options(BillingCycle::class)->default('yearly')->required(),
                ]),

            TextInput::make('quantity')
                ->label(__('Quantity'))
                ->numeric()
                ->minValue(1)
                ->default(1)
                ->required()
                ->live(onBlur: true),

            TextInput::make('cost_price')
                ->label(__('Cost (per unit)'))
                ->numeric()
                ->prefix('€')
                ->default(0)
                ->disabled(fn (Get $get) => filled($get('purchase_bundle_id')))
                ->required(fn (Get $get) => blank($get('purchase_bundle_id')))
                ->helperText(fn (Get $get) => filled($get('purchase_bundle_id'))
                    ? 'Allocated automatically from the purchase bundle.'
                    : null)
                ->live(onBlur: true),

            TextInput::make('sale_price')
                ->label(__('Sale (per unit)'))
                ->numeric()
                ->prefix('€')
                ->default(0)
                ->required()
                ->live(onBlur: true),

            Select::make('currency')
                ->label(__('Currency'))
                ->options(['EUR' => 'EUR', 'USD' => 'USD', 'GBP' => 'GBP'])
                ->default('EUR')
                ->required(),

            Select::make('billing_cycle')
                ->label(__('Billing cycle'))
                ->options(BillingCycle::class)
                ->default('yearly')
                ->required()
                ->live()
                ->helperText(function (Get $get): ?string {
                    $cycle = $get('billing_cycle');
                    $value = $cycle instanceof BillingCycle ? $cycle : BillingCycle::tryFrom((string) $cycle);

                    return $value === BillingCycle::Once
                        ? 'One-time services cannot use portal auto-collect.'
                        : 'Recurring services: the customer can enable collection in /portal (iDEAL → SEPA).';
                }),

            TextInput::make('margin_preview')
                ->label(__('Margin (estimate)'))
                ->disabled()
                ->dehydrated(false)
                ->prefix('€')
                ->helperText(function (Get $get): string {
                    $qty = (float) ($get('quantity') ?: 0);
                    $sale = $qty * (float) ($get('sale_price') ?: 0);
                    $cost = $qty * (float) ($get('cost_price') ?: 0);
                    $pct = $sale > 0 ? round((($sale - $cost) / $sale) * 100, 1) : 0;

                    return "Margin %: {$pct}%";
                })
                ->afterStateHydrated(function (Get $get, Set $set): void {
                    $qty = (float) ($get('quantity') ?: 0);
                    $set('margin_preview', number_format($qty * ((float) $get('sale_price') - (float) $get('cost_price')), 2, '.', ''));
                }),
        ];
    }

    /** @return array<int, \Filament\Schemas\Components\Component> */
    public static function looptijdComponents(): array
    {
        return [
            DatePicker::make('start_date')
                ->label(__('Start date'))
                ->default(now())
                ->required(),

            DatePicker::make('renewal_date')
                ->label(__('Renewal / end date')),

            TextInput::make('notice_period_days')
                ->label(__('Notice period (days)'))
                ->numeric()
                ->default(30)
                ->required(),

            Toggle::make('auto_renew')
                ->label(__('Auto-renew'))
                ->default(true),

            Toggle::make('notify_renewals')
                ->label(__('Customer email for this license'))
                ->default(true)
                ->helperText(__('Only if the customer also has renewal email enabled.')),

            Select::make('status')
                ->label(__('Status'))
                ->options(ContractStatus::class)
                ->default('active')
                ->required(),

            DatePicker::make('next_invoice_date')
                ->label(__('Next invoice date')),
        ];
    }

    /**
     * Stripe auto-collect is customer-driven via /portal. Admin fields are read-only
     * so operators can see status without accidentally flipping the mandate.
     *
     * @return array<int, \Filament\Schemas\Components\Component>
     */
    public static function incassoComponents(): array
    {
        return [
            Toggle::make('auto_collect')
                ->label(__('Auto-collect'))
                ->disabled()
                ->dehydrated(false)
                ->helperText(__('Customers enable this in the portal (iDEAL → SEPA mandate). Do not turn it on by hand.')),

            TextInput::make('stripe_payment_status')
                ->label(__('Stripe payment status'))
                ->disabled()
                ->dehydrated(false)
                ->placeholder(__('off')),

            DateTimePicker::make('auto_collect_enabled_at')
                ->label(__('Collection enabled at'))
                ->disabled()
                ->dehydrated(false)
                ->seconds(false),

            TextInput::make('stripe_subscription_id')
                ->label(__('Stripe subscription ID'))
                ->disabled()
                ->dehydrated(false)
                ->placeholder(__('—')),
        ];
    }

    /** @return array<int, \Filament\Schemas\Components\Component> */
    public static function licentiesComponents(): array
    {
        return [
            Textarea::make('license_keys')
                ->label(__('License keys (stored encrypted)'))
                ->rows(3)
                ->columnSpanFull(),

            Textarea::make('notes')
                ->label(__('Notes'))
                ->rows(3)
                ->columnSpanFull(),
        ];
    }
}
