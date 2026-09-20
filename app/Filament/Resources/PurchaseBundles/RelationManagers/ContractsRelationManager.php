<?php

namespace App\Filament\Resources\PurchaseBundles\RelationManagers;

use App\Enums\ContractStatus;
use App\Filament\Resources\Contracts\ContractResource;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ContractsRelationManager extends RelationManager
{
    protected static string $relationship = 'contracts';

    protected static ?string $title = 'Linked customer contracts';

    public function isReadOnly(): bool
    {
        return ! (auth()->user()?->canManageContracts() ?? false);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->recordUrl(fn ($record) => ContractResource::getUrl('view', ['record' => $record]))
            ->columns([
                TextColumn::make('company.name')->label(__('Company'))->weight('bold'),
                TextColumn::make('name')->label(__('Service / package'))->visibleFrom('md'),
                TextColumn::make('effective_cost')
                    ->label(__('Allocated cost'))
                    ->money('EUR')
                    ->alignEnd()
                    ->visibleFrom('lg'),
                TextColumn::make('total_sale')->label(__('Sale'))->money('EUR')->alignEnd()->visibleFrom('md'),
                TextColumn::make('margin_eur')
                    ->label(__('Margin €'))
                    ->money('EUR')
                    ->alignEnd()
                    ->color(fn ($state) => $state > 0 ? 'success' : 'danger')
                    ->visibleFrom('lg'),
                TextColumn::make('status')->label(__('Status'))->badge(),
            ])
            ->modifyQueryUsing(fn ($query) => $query->orderByRaw(
                "CASE WHEN status = '" . ContractStatus::Active->value . "' THEN 0 ELSE 1 END"
            ));
    }
}
