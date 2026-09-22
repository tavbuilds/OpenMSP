<?php

namespace App\Filament\Resources\Products\Tables;

use App\Enums\ProductType;
use App\Filament\Resources\Products\ProductResource;
use App\Support\Breakpoints;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ProductsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->recordUrl(fn ($record) => ProductResource::getUrl('view', ['record' => $record]))
            ->columns([
                TextColumn::make('name')->label(__('Name'))->searchable()->sortable()->weight('bold'),
                TextColumn::make('vendor.name')->label(__('Vendor'))->searchable()->placeholder(__('—'))->visibleFrom(Breakpoints::COLUMN_SECONDARY),
                TextColumn::make('sku')->label(__('SKU'))->searchable()->toggleable()->visibleFrom(Breakpoints::COLUMN_WIDEST),
                TextColumn::make('type')->label(__('Type'))->badge()->visibleFrom(Breakpoints::COLUMN_SECONDARY),
                TextColumn::make('effective_cost_price')
                    ->label(__('Cost'))
                    ->money('EUR')
                    ->alignEnd()
                    ->tooltip(fn ($record) => $record->isComposite() ? 'Sum of linked components' : null)
                    ->icon(fn ($record) => $record->isComposite() ? 'heroicon-m-squares-plus' : null)
                    ->visibleFrom(Breakpoints::COLUMN_TERTIARY),
                TextColumn::make('default_sale_price')->label(__('Sale'))->money('EUR')->alignEnd()->sortable(),
                TextColumn::make('margin_eur')
                    ->label(__('Margin €'))
                    ->money('EUR')
                    ->alignEnd()
                    ->color(fn ($state) => $state > 0 ? 'success' : 'danger')
                    ->visibleFrom(Breakpoints::COLUMN_WIDEST),
                TextColumn::make('margin_pct')
                    ->label(__('Margin %'))
                    ->suffix('%')
                    ->alignEnd()
                    ->badge()
                    ->color(fn ($state) => $state >= 30 ? 'success' : ($state >= 15 ? 'warning' : 'danger'))
                    ->visibleFrom(Breakpoints::COLUMN_SECONDARY),
                TextColumn::make('billing_cycle')->label(__('Billing'))->badge()->visibleFrom(Breakpoints::COLUMN_TERTIARY),
                IconColumn::make('active')->label(__('Active'))->boolean(),
            ])
            ->filters([
                SelectFilter::make('type')->label(__('Type'))->options(ProductType::class),
                SelectFilter::make('vendor_id')->label(__('Vendor'))->relationship('vendor', 'name'),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
