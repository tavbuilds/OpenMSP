<?php

namespace App\Filament\Resources\Products\Schemas;

use App\Enums\ContractStatus;
use App\Models\Product;
use App\Support\Breakpoints;
use App\Support\Money;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class ProductInfolist
{
    /**
     * Price on top, the rest in tabs.
     *
     * Two of the four cards only exist for a bundle or a Pax8 import, so in
     * the common case the page was mostly empty columns. As tabs they cost
     * nothing when they are not there.
     */
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make(__('Price & margin'))
                    ->columns(Breakpoints::FOUR)
                    ->schema([
                        TextEntry::make('effective_cost_price')
                            ->label(__('Effective cost'))
                            ->money('EUR')
                            ->helperText(fn (Product $record) => $record->isComposite()
                                ? __('Sum of components')
                                : ($record->defaultPriceOption()
                                    ? __('Monthly list price (:term)', ['term' => $record->defaultPriceOption()->label()])
                                    : null)),
                        TextEntry::make('default_sale_price')->label(__('Sale'))->money('EUR'),
                        TextEntry::make('margin_eur')
                            ->label(__('Margin €'))
                            ->money('EUR')
                            ->color(fn ($state) => $state > 0 ? 'success' : 'danger'),
                        TextEntry::make('margin_pct')
                            ->label(__('Margin %'))
                            ->suffix('%')
                            ->badge()
                            ->color(fn ($state) => $state >= 30 ? 'success' : ($state >= 15 ? 'warning' : 'danger')),
                    ]),

                Tabs::make()
                    ->columnSpanFull()
                    ->tabs([
                        Tab::make(__('Details'))
                            ->icon(Heroicon::OutlinedCube)
                            ->columns(Breakpoints::THREE)
                            ->schema([
                                TextEntry::make('name')->label(__('Name')),
                                TextEntry::make('vendor.name')->label(__('Vendor'))->placeholder(__('—')),
                                TextEntry::make('sku')->label(__('SKU'))->placeholder(__('—')),
                                TextEntry::make('type')->label(__('Type'))->badge(),
                                TextEntry::make('billing_cycle')->label(__('Billing cycle'))->badge(),
                                TextEntry::make('currency')->label(__('Currency')),
                                IconEntry::make('active')->label(__('Active in catalog'))->boolean(),
                                TextEntry::make('active_contracts')
                                    ->label(__('Active contracts with this product'))
                                    ->state(fn (Product $record) => $record->contracts()
                                        ->where('status', ContractStatus::Active->value)->count()),
                                TextEntry::make('description')
                                    ->label(__('Description'))
                                    ->placeholder(__('—'))
                                    ->columnSpanFull(),
                            ]),

                        Tab::make(__('Components'))
                            ->icon(Heroicon::OutlinedSquares2x2)
                            ->badge(fn (Product $record) => $record->components->count() ?: null)
                            ->visible(fn (Product $record) => $record->isComposite())
                            ->schema([
                                RepeatableEntry::make('components')
                                    ->hiddenLabel()
                                    ->columns(Breakpoints::THREE)
                                    ->schema([
                                        TextEntry::make('name')->label(__('Component')),
                                        TextEntry::make('pivot.quantity')->label(__('Quantity')),
                                        TextEntry::make('effective_cost_price')->label(__('Cost / unit'))->money('EUR'),
                                    ]),
                            ]),

                        Tab::make(__('Pax8 price options'))
                            ->icon(Heroicon::OutlinedArrowsRightLeft)
                            ->badge(fn (Product $record) => $record->priceOptions->count() ?: null)
                            ->visible(fn (Product $record) => $record->priceOptions->isNotEmpty())
                            ->schema([
                                TextEntry::make('price_options_hint')
                                    ->hiddenLabel()
                                    ->columnSpanFull()
                                    ->state(__('Sellable rates Pax8 assigns to this partner (Flat). Trials and other pricing models are omitted. The catalog table uses 1-year / monthly when that exists.')),

                                RepeatableEntry::make('priceOptions')
                                    ->hiddenLabel()
                                    ->columns([
                                        'default' => 1,
                                        'md' => 2,
                                        'xl' => 6,
                                    ])
                                    ->schema([
                                        TextEntry::make('commitment_term')->label(__('Commitment')),
                                        TextEntry::make('billing_term')->label(__('Billing')),
                                        TextEntry::make('cost_price')
                                            ->label(__('Cost'))
                                            ->formatStateUsing(fn ($state) => Money::format($state, precision: 4)),
                                        TextEntry::make('sale_price')
                                            ->label(__('Price'))
                                            ->formatStateUsing(fn ($state) => Money::format($state)),
                                        TextEntry::make('qty')
                                            ->label(__('Min/max'))
                                            ->state(fn ($record) => $record->qtyLabel()),
                                        TextEntry::make('is_default')
                                            ->label(__('List price'))
                                            ->badge()
                                            ->formatStateUsing(fn ($state) => $state ? __('Yes') : __('No'))
                                            ->color(fn ($state) => $state ? 'success' : 'gray'),
                                    ]),
                            ]),
                    ]),
            ]);
    }
}
