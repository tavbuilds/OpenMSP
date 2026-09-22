<?php

namespace App\Filament\Resources\PurchaseBundles\Tables;

use App\Filament\Resources\PurchaseBundles\PurchaseBundleResource;
use App\Support\Breakpoints;
use App\Support\Money;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PurchaseBundlesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->recordUrl(fn ($record) => PurchaseBundleResource::getUrl('view', ['record' => $record]))
            ->columns([
                TextColumn::make('name')->label(__('Bundle'))->searchable()->sortable()->weight('bold'),
                TextColumn::make('vendor.name')->label(__('Vendor'))->placeholder(__('—'))->searchable()->visibleFrom(Breakpoints::COLUMN_SECONDARY),
                TextColumn::make('total_cost')->label(__('Total cost'))->money('EUR')->alignEnd()->sortable(),
                TextColumn::make('billing_cycle')->label(__('Cycle'))->badge()->visibleFrom(Breakpoints::COLUMN_SECONDARY),
                TextColumn::make('active_contracts')
                    ->label(__('Active contracts'))
                    ->state(fn ($record) => $record->activeContractsCount())
                    ->badge()
                    ->alignEnd()
                    ->visibleFrom(Breakpoints::COLUMN_TERTIARY),
                TextColumn::make('allocated_per_contract')
                    ->label(__('Cost / contract'))
                    ->state(fn ($record) => Money::format($record->allocatedAnnualCostPerContract()).' '.__('/yr'))
                    ->alignEnd()
                    ->visibleFrom(Breakpoints::COLUMN_WIDEST),
                TextColumn::make('recovery')
                    ->label(__('Coverage'))
                    ->state(fn ($record) => $record->recoveryPercentage().'%')
                    ->badge()
                    ->color(fn ($record) => $record->recoveryPercentage() >= 100 ? 'success' : ($record->recoveryPercentage() >= 60 ? 'warning' : 'danger'))
                    ->alignEnd(),
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
