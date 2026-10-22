<?php

namespace App\Filament\Resources\Contracts\Tables;

use App\Enums\BillingCycle;
use App\Enums\ContractStatus;
use App\Enums\ProductType;
use App\Filament\Resources\Contracts\ContractResource;
use App\Models\Contract;
use App\Support\Breakpoints;
use App\Support\ContractGroups;
use App\Support\ContractMoves;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Throwable;

class ContractsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            // Services live inside their collection's own page. Listing
            // them here too would show the same agreement several times and
            // bury the thing that actually renews.
            ->modifyQueryUsing(fn (Builder $query) => $query->topLevel())
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
                    ->icon(fn ($record) => $record->isGroup() ? 'heroicon-m-rectangle-stack' : null)
                    ->searchable(),

                TextColumn::make('services_count')
                    ->label(__('Services'))
                    ->counts('services')
                    ->badge()
                    ->placeholder(__('—'))
                    ->visible(fn () => Contract::query()->where('is_group', true)->exists())
                    ->formatStateUsing(fn ($state, $record) => $record->isGroup()
                        ? trans_choice('{0} empty|{1} :count service|[2,*] :count services', (int) $state, ['count' => $state])
                        : null),

                TextColumn::make('type')
                    ->label(__('Type'))
                    ->badge()
                    ->visibleFrom(Breakpoints::COLUMN_WIDEST),

                TextColumn::make('effective_cost')
                    ->label(__('Cost'))
                    ->money('EUR')
                    ->alignEnd()
                    ->tooltip(fn ($record) => $record->uses_bundle
                        ? 'Allocated from bundle: '.$record->purchaseBundle?->name
                        : null)
                    ->icon(fn ($record) => $record->uses_bundle ? 'heroicon-m-squares-2x2' : null)
                    ->toggleable()
                    ->visibleFrom(Breakpoints::COLUMN_WIDEST),

                TextColumn::make('total_sale')
                    ->label(__('Sale'))
                    ->money('EUR')
                    ->alignEnd()
                    ->visibleFrom(Breakpoints::COLUMN_SECONDARY),

                TextColumn::make('margin_eur')
                    ->label(__('Margin €'))
                    ->money('EUR')
                    ->alignEnd()
                    ->color(fn ($state) => $state > 0 ? 'success' : 'danger')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('margin_pct')
                    ->label(__('Margin %'))
                    ->suffix('%')
                    ->alignEnd()
                    ->badge()
                    ->color(fn ($state) => $state >= 30 ? 'success' : ($state >= 15 ? 'warning' : 'danger'))
                    ->visibleFrom(Breakpoints::COLUMN_TERTIARY),

                TextColumn::make('billing_cycle')
                    ->label(__('Billing'))
                    ->badge()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('start_date')
                    ->label(__('Start'))
                    ->date('M j, Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('renewal_date')
                    ->label(__('Renewal'))
                    ->date('M j, Y')
                    ->sortable()
                    ->color(fn ($record) => $record->renewal_date && $record->renewal_date->isBefore(now()->addDays(30)) ? 'danger' : null),

                IconColumn::make('auto_renew')
                    ->label(__('Auto-renew'))
                    ->boolean()
                    ->visibleFrom(Breakpoints::COLUMN_WIDEST),

                IconColumn::make('auto_collect')
                    ->label(__('Collection'))
                    ->boolean()
                    ->tooltip(fn ($record) => $record->stripePaymentStatusLabel())
                    ->visibleFrom(Breakpoints::COLUMN_SECONDARY),

                TextColumn::make('stripe_payment_status')
                    ->label(__('Collection status'))
                    ->badge()
                    ->placeholder(__('Off'))
                    ->formatStateUsing(fn ($record) => $record->stripePaymentStatusLabel())
                    ->color(fn ($record) => $record->stripePaymentStatusColor())
                    ->toggleable(isToggledHiddenByDefault: true),

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

                TernaryFilter::make('is_group')
                    ->label(__('Collections'))
                    ->trueLabel(__('Only collections'))
                    ->falseLabel(__('Only single services')),

                Filter::make('sale_price_range')
                    ->label(__('Sale amount'))
                    ->schema([
                        TextInput::make('sale_from')->label(__('From'))->numeric(),
                        TextInput::make('sale_to')->label(__('To'))->numeric(),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['sale_from'] ?? null, fn (Builder $q, $v) => $q->where('sale_price', '>=', $v))
                            ->when($data['sale_to'] ?? null, fn (Builder $q, $v) => $q->where('sale_price', '<=', $v));
                    }),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    self::groupAction(),
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    /**
     * Gathers loose services into one agreement.
     *
     * Most installs already hold a row per service, each renewing on its own
     * date, so this has to work on the data as it stands — selecting those
     * rows and naming the thing they really are. Select a collection along
     * with them and they join it instead of forming a second one.
     */
    private static function groupAction(): BulkAction
    {
        return BulkAction::make('groupContracts')
            ->label(__('Combine into one contract'))
            ->icon('heroicon-o-rectangle-stack')
            ->modalHeading(__('Combine into one contract'))
            ->modalDescription(fn (Collection $records): string => implode(' ', [
                __('The services keep their own prices. The term you set here applies to all of them.'),
                ...ContractMoves::warnings($records),
            ]))
            ->modalSubmitActionLabel(__('Combine'))
            ->visible(fn () => auth()->user()?->canManageContracts() ?? false)
            ->schema([
                TextInput::make('name')
                    ->label(__('Contract name'))
                    ->placeholder(__('Website hosting & domains'))
                    ->required()
                    ->maxLength(120),
                Select::make('billing_cycle')
                    ->label(__('Billing cycle'))
                    ->options(BillingCycle::class)
                    ->default(BillingCycle::Yearly->value)
                    ->required(),
                DatePicker::make('start_date')
                    ->label(__('Start date'))
                    ->default(now()),
                DatePicker::make('renewal_date')
                    ->label(__('Renewal date'))
                    ->helperText(__('When the whole contract comes up for renewal.')),
                TextInput::make('notice_period_days')
                    ->label(__('Notice period (days)'))
                    ->numeric()
                    ->default(0)
                    ->minValue(0),
                Toggle::make('auto_renew')
                    ->label(__('Renews automatically'))
                    ->default(true),
            ])
            ->action(function (Collection $records, array $data): void {
                try {
                    $group = ContractGroups::merge($records, $data);
                } catch (Throwable $e) {
                    Notification::make()
                        ->title(__('Nothing was combined'))
                        ->body($e->getMessage())
                        ->danger()
                        ->persistent()
                        ->send();

                    return;
                }

                Notification::make()
                    ->title(__('Contract created'))
                    ->body(trans_choice(
                        '{1} :name now holds :count service.|[2,*] :name now holds :count services.',
                        $group->services()->count(),
                        ['name' => $group->name, 'count' => $group->services()->count()],
                    ))
                    ->success()
                    ->send();
            })
            ->deselectRecordsAfterCompletion();
    }
}
