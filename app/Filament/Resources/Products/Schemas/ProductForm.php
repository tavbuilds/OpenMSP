<?php

namespace App\Filament\Resources\Products\Schemas;

use App\Enums\BillingCycle;
use App\Enums\ProductType;
use App\Models\Product;
use App\Support\Breakpoints;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class ProductForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Product / service')
                    ->columns(Breakpoints::TWO)
                    ->schema([
                        TextInput::make('name')->label(__('Name'))->required(),
                        TextInput::make('sku')->label(__('SKU')),
                        Select::make('vendor_id')
                            ->label(__('Vendor'))
                            ->relationship('vendor', 'name')
                            ->searchable()
                            ->preload()
                            ->createOptionForm([
                                TextInput::make('name')->label(__('Name'))->required(),
                                TextInput::make('website'),
                            ]),
                        Select::make('type')->label(__('Type'))->options(ProductType::class)->default('license')->required(),
                        Toggle::make('active')->label(__('Active in catalog'))->default(true),
                    ]),

                Section::make('Components (bundle product)')
                    ->description(__('Optional. Attach other catalog products as components. This bundle’s cost then becomes the sum of its parts — a component price change flows through everywhere.'))
                    ->collapsed(fn (?Product $record) => ! $record?->isComposite())
                    ->schema([
                        Repeater::make('componentLinks')
                            ->relationship()
                            ->label(__('Components'))
                            ->addActionLabel('Add component')
                            ->live()
                            ->columns(Breakpoints::TWO)
                            ->schema([
                                Select::make('component_id')
                                    ->label(__('Component'))
                                    ->relationship('component', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->distinct(),
                                TextInput::make('quantity')
                                    ->label(__('Quantity'))
                                    ->numeric()
                                    ->minValue(1)
                                    ->default(1)
                                    ->required(),
                            ]),

                        TextInput::make('computed_cost_preview')
                            ->label(__('Cost from components'))
                            ->prefix('€')
                            ->disabled()
                            ->dehydrated(false)
                            ->helperText(__('Sum of components × quantity. Replaces the fixed cost price once components are set.'))
                            ->afterStateHydrated(fn (TextInput $component, Get $get) => $component->state(self::computeCost($get)))
                            ->reactive()
                            ->formatStateUsing(fn (Get $get) => self::computeCost($get)),
                    ]),

                Section::make('Default prices')
                    ->columns(Breakpoints::THREE)
                    ->schema([
                        TextInput::make('default_cost_price')
                            ->label(__('Cost price (fixed)'))
                            ->numeric()->prefix('€')->default(0)->required()
                            ->helperText(__('Ignored when the product has components (the component sum is used instead).')),
                        TextInput::make('default_sale_price')->label(__('Sale price'))->numeric()->prefix('€')->default(0)->required(),
                        Select::make('currency')->label(__('Currency'))->options(['EUR' => 'EUR', 'USD' => 'USD', 'GBP' => 'GBP'])->default('EUR')->required(),
                        Select::make('billing_cycle')->label(__('Billing cycle'))->options(BillingCycle::class)->default('yearly')->required(),
                    ]),

                Section::make('Description')
                    ->collapsed()
                    ->schema([
                        Textarea::make('description')->label(__('Description'))->columnSpanFull(),
                    ]),
            ]);
    }

    /** Live cost calculation from the selected components. */
    protected static function computeCost(Get $get): string
    {
        $links = $get('componentLinks') ?? [];
        $total = 0.0;

        foreach ($links as $link) {
            $id = $link['component_id'] ?? null;
            $qty = (int) ($link['quantity'] ?? 1);
            if ($id && $product = Product::find($id)) {
                $total += $product->effective_cost_price * $qty;
            }
        }

        return number_format($total, 2, '.', '');
    }
}
