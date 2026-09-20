<?php

namespace App\Filament\Resources\Products\Schemas;

use App\Enums\ContractStatus;
use App\Models\Product;
use App\Support\Breakpoints;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ProductInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Price & margin')
                    ->columns(Breakpoints::FOUR)
                    ->schema([
                        TextEntry::make('effective_cost_price')
                            ->label(__('Effective cost'))
                            ->money('EUR')
                            ->helperText(fn (Product $record) => $record->isComposite() ? 'Sum of components' : null),
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

                Section::make('Components')
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

                Section::make('Product details')
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
                    ]),

                Section::make('Description')
                    ->collapsed()
                    ->schema([
                        TextEntry::make('description')->hiddenLabel()->placeholder(__('—'))->columnSpanFull(),
                    ]),
            ]);
    }
}
