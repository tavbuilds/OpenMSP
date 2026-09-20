<?php

namespace App\Filament\Resources\PurchaseBundles\Tables;

use App\Filament\Resources\PurchaseBundles\PurchaseBundleResource;
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
                TextColumn::make('vendor.name')->label(__('Vendor'))->placeholder(__('—'))->searchable()->visibleFrom('md'),
                TextColumn::make('total_cost')->label(__('Total cost'))->money('EUR')->alignEnd()->sortable(),
                TextColumn::make('billing_cycle')->label(__('Cycle'))->badge()->visibleFrom('md'),
                TextColumn::make('active_contracts')
                    ->label(__('Active contracts'))
                    ->state(fn ($record) => $record->activeContractsCount())
                    ->badge()
                    ->alignEnd()
                    ->visibleFrom('lg'),
                TextColumn::make('allocated_per_contract')
                    ->label(__('Cost / contract'))
                    ->state(fn ($record) => '€ ' . number_format($record->allocatedAnnualCostPerContract(), 2) . ' /yr')
                    ->alignEnd()
                    ->visibleFrom('lg'),
                TextColumn::make('recovery')
                    ->label(__('Coverage'))
                    ->state(fn ($record) => $record->recoveryPercentage() . '%')
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
