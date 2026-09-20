<?php

namespace App\Filament\Resources\Companies\RelationManagers;

use App\Enums\ContractStatus;
use App\Enums\ProductType;
use App\Filament\Resources\Contracts\ContractResource;
use Filament\Actions\CreateAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ContractsRelationManager extends RelationManager
{
    protected static string $relationship = 'contracts';

    protected static ?string $title = 'Contracts & licenses';

    public function isReadOnly(): bool
    {
        return ! (auth()->user()?->canManageContracts() ?? false);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->defaultSort('renewal_date', 'asc')
            ->recordUrl(fn ($record) => ContractResource::getUrl('view', ['record' => $record]))
            ->columns([
                TextColumn::make('name')->label(__('Service / package'))->weight('bold')
                    ->description(fn ($record) => $record->reference),
                TextColumn::make('type')->label(__('Type'))->badge()->visibleFrom('lg'),
                TextColumn::make('quantity')->label(__('Quantity'))->alignEnd()->visibleFrom('xl'),
                TextColumn::make('total_sale')->label(__('Sale'))->money('EUR')->alignEnd()->visibleFrom('md'),
                TextColumn::make('margin_eur')->label(__('Margin €'))->money('EUR')->alignEnd()
                    ->color(fn ($state) => $state > 0 ? 'success' : 'danger')
                    ->visibleFrom('lg'),
                TextColumn::make('renewal_date')->label(__('Renewal'))->date('M j, Y')->sortable(),
                IconColumn::make('auto_renew')->label(__('Auto'))->boolean()->visibleFrom('md'),
                IconColumn::make('auto_collect')->label(__('Collection'))->boolean()
                    ->tooltip(fn ($record) => $record->stripePaymentStatusLabel())
                    ->visibleFrom('md'),
                TextColumn::make('stripe_payment_status')
                    ->label(__('Payment status'))
                    ->badge()
                    ->placeholder(__('Off'))
                    ->formatStateUsing(fn ($record) => $record->stripePaymentStatusLabel())
                    ->color(fn ($record) => $record->stripePaymentStatusColor())
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('status')->label(__('Status'))->badge(),
            ])
            ->filters([
                SelectFilter::make('status')->label(__('Status'))->options(ContractStatus::class),
                SelectFilter::make('type')->label(__('Type'))->options(ProductType::class),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label(__('Add contract'))
                    ->url(fn () => ContractResource::getUrl('create')),
            ]);
    }
}
