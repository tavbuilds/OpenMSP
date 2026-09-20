<?php

namespace App\Filament\Resources\Contracts\Tables;

use App\Enums\BillingCycle;
use App\Enums\ContractStatus;
use App\Enums\ProductType;
use App\Filament\Resources\Contracts\ContractResource;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ContractsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('renewal_date', 'asc')
            ->recordUrl(fn ($record) => ContractResource::getUrl('view', ['record' => $record]))
            ->columns([
                TextColumn::make('company.name')
                    ->label(__('Company'))
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('name')
                    ->label(__('Service / package'))
                    ->description(fn ($record) => $record->reference)
                    ->searchable(),

                TextColumn::make('type')
                    ->label(__('Type'))
                    ->badge()
                    ->visibleFrom('lg'),

                TextColumn::make('effective_cost')
                    ->label(__('Cost'))
                    ->money('EUR')
                    ->alignEnd()
                    ->tooltip(fn ($record) => $record->uses_bundle
                        ? 'Allocated from bundle: ' . $record->purchaseBundle?->name
                        : null)
                    ->icon(fn ($record) => $record->uses_bundle ? 'heroicon-m-squares-2x2' : null)
                    ->toggleable()
                    ->visibleFrom('lg'),

                TextColumn::make('total_sale')
                    ->label(__('Sale'))
                    ->money('EUR')
                    ->alignEnd()
                    ->visibleFrom('md'),

                TextColumn::make('margin_eur')
                    ->label(__('Margin €'))
                    ->money('EUR')
                    ->alignEnd()
                    ->color(fn ($state) => $state > 0 ? 'success' : 'danger')
                    ->visibleFrom('lg'),

                TextColumn::make('margin_pct')
                    ->label(__('Margin %'))
                    ->suffix('%')
                    ->alignEnd()
                    ->badge()
                    ->color(fn ($state) => $state >= 30 ? 'success' : ($state >= 15 ? 'warning' : 'danger'))
                    ->visibleFrom('lg'),

                TextColumn::make('billing_cycle')
                    ->label(__('Billing'))
                    ->badge()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('start_date')
                    ->label(__('Start'))
                    ->date('M j, Y')
                    ->sortable()
                    ->visibleFrom('xl'),

                TextColumn::make('renewal_date')
                    ->label(__('Renewal'))
                    ->date('M j, Y')
                    ->sortable()
                    ->color(fn ($record) => $record->renewal_date && $record->renewal_date->isBefore(now()->addDays(30)) ? 'danger' : null),

                IconColumn::make('auto_renew')
                    ->label(__('Auto-renew'))
                    ->boolean()
                    ->visibleFrom('md'),

                IconColumn::make('auto_collect')
                    ->label(__('Collection'))
                    ->boolean()
                    ->tooltip(fn ($record) => $record->stripePaymentStatusLabel())
                    ->visibleFrom('md'),

                TextColumn::make('stripe_payment_status')
                    ->label(__('Collection status'))
                    ->badge()
                    ->placeholder(__('Off'))
                    ->formatStateUsing(fn ($record) => $record->stripePaymentStatusLabel())
                    ->color(fn ($record) => $record->stripePaymentStatusColor())
                    ->toggleable()
                    ->visibleFrom('xl'),

                TextColumn::make('status')
                    ->label(__('Status'))
                    ->badge(),
            ])
            ->filters([
                SelectFilter::make('company_id')
                    ->label(__('Company'))
                    ->relationship('company', 'name')
                    ->searchable()
                    ->preload(),

                SelectFilter::make('type')
                    ->label(__('Type'))
                    ->options(ProductType::class),

                SelectFilter::make('status')
                    ->label(__('Status'))
                    ->options(ContractStatus::class),

                SelectFilter::make('billing_cycle')
                    ->label(__('Billing cycle'))
                    ->options(BillingCycle::class),

                TernaryFilter::make('auto_renew')
                    ->label(__('Auto-renew')),

                TernaryFilter::make('auto_collect')
                    ->label(__('Auto-collect')),

                Filter::make('sale_price_range')
                    ->label(__('Salebedrag'))
                    ->schema([
                        TextInput::make('sale_from')->label(__('Vanaf (€)'))->numeric(),
                        TextInput::make('sale_to')->label(__('Tot (€)'))->numeric(),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['sale_from'] ?? null, fn (Builder $q, $v) => $q->where('sale_price', '>=', $v))
                            ->when($data['sale_to'] ?? null, fn (Builder $q, $v) => $q->where('sale_price', '<=', $v));
                    }),
            ], layout: FiltersLayout::AboveContentCollapsible)
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
