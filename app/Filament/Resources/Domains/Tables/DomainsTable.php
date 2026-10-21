<?php

namespace App\Filament\Resources\Domains\Tables;

use App\Enums\DomainSource;
use App\Filament\Resources\Domains\DomainResource;
use App\Models\Company;
use App\Models\Domain;
use App\Support\Breakpoints;
use App\Support\Money;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class DomainsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            // Sorted on the date the countdown runs to, so the Days column
            // reads top to bottom instead of jumping about.
            ->defaultSort(fn (Builder $query, string $direction) => $query->orderByDue($direction))
            ->recordUrl(fn (Domain $record) => DomainResource::getUrl('view', ['record' => $record]))
            ->columns([
                TextColumn::make('name')->label(__('Domain name'))->searchable()->weight('bold'),
                TextColumn::make('company.name')
                    ->label(__('Customer'))
                    ->placeholder(__('Not assigned'))
                    ->searchable()
                    ->sortable(),
                // Openprovider's renewal_date, not its expiration_date: their
                // own spec calls the latter "not the primary reference".
                TextColumn::make('due')
                    ->label(__('Renews'))
                    ->state(fn (Domain $record) => $record->expiryDate())
                    ->date('M j, Y')
                    ->placeholder(__('—')),
                TextColumn::make('days')
                    ->label(__('Days'))
                    ->state(fn (Domain $record) => $record->daysUntilExpiry())
                    ->badge()
                    ->color(fn (Domain $record) => $record->statusColor())
                    ->tooltip(fn (Domain $record) => $record->statusLabel()),
                TextColumn::make('status')
                    ->label(__('At the registrar'))
                    ->badge()
                    ->placeholder(__('—'))
                    // A domain that ran out of quarantine still carries its
                    // last known status; say it is gone instead.
                    ->state(fn (Domain $record) => $record->isRemoved()
                        ? __('No longer at the registrar')
                        : $record->registrarStatusLabel())
                    ->color(fn (Domain $record) => $record->registrarStatusColor())
                    ->visibleFrom(Breakpoints::COLUMN_SECONDARY),
                IconColumn::make('auto_renew')
                    ->label(__('Auto-renew'))
                    ->boolean()
                    ->tooltip(fn (Domain $record) => $record->autoRenewLabel())
                    ->visibleFrom(Breakpoints::COLUMN_SECONDARY),
                TextColumn::make('cost')
                    ->label(__('Purchase / year'))
                    ->alignEnd()
                    ->state(fn (Domain $record) => $record->costPrice() !== null
                        ? Money::format($record->costPrice(), $record->product?->currency)
                        : null)
                    ->placeholder(__('—'))
                    ->visibleFrom(Breakpoints::COLUMN_TERTIARY),
                TextColumn::make('expires_at')
                    ->label(__('Registry expiry'))
                    ->date('M j, Y')
                    ->sortable()
                    ->placeholder(__('—'))
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('removed_at')
                    ->label(__('Gone since'))
                    ->date('M j, Y')
                    ->placeholder(__('—'))
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('extension')
                    ->label(__('Extension'))
                    ->prefix('.')
                    ->toggleable()
                    ->visibleFrom(Breakpoints::COLUMN_WIDEST),
                TextColumn::make('source')->label(__('Source'))->badge()->visibleFrom(Breakpoints::COLUMN_WIDEST),
            ])
            ->filters([
                // Default view is what we still manage. Nothing is deleted,
                // so the other two options get you back to everything.
                SelectFilter::make('lifecycle')
                    ->label(__('At the registrar'))
                    ->options([
                        'present' => __('Current'),
                        'removed' => __('No longer at the registrar'),
                        'all' => __('All'),
                    ])
                    ->default('present')
                    ->selectablePlaceholder(false)
                    ->query(fn (Builder $query, array $data) => match ($data['value'] ?? null) {
                        'removed' => $query->removed(),
                        'present' => $query->present(),
                        // "All", and the × on the filter bar, both mean
                        // everything — clearing a filter has to clear it.
                        default => $query,
                    }),
                SelectFilter::make('company_id')->label(__('Customer'))->relationship('company', 'name')->searchable()->preload(),
                SelectFilter::make('source')->label(__('Source'))->options(DomainSource::class),
                // The first thing to do after an import is empty this filter.
                Filter::make('unassigned')
                    ->label(__('Not assigned to a customer'))
                    ->query(fn ($q) => $q->whereNull('company_id')),
                TernaryFilter::make('auto_renew')->label(__('Auto-renew')),
                TernaryFilter::make('expired')
                    ->label(__('Expired'))
                    ->queries(
                        true: fn ($q) => $q->whereRaw(Domain::ACTIONABLE_DATE.' < ?', [now()->toDateString()]),
                        false: fn ($q) => $q->whereRaw(Domain::ACTIONABLE_DATE.' >= ?', [now()->toDateString()]),
                    ),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    // After the first import every domain is unassigned;
                    // clicking through them one by one is the difference
                    // between using this and not.
                    BulkAction::make('assignCompany')
                        ->label(__('Assign to customer'))
                        ->icon('heroicon-o-building-office-2')
                        ->visible(fn () => auth()->user()?->canManageContracts() ?? false)
                        ->schema([
                            Select::make('company_id')
                                ->label(__('Customer'))
                                ->options(fn () => Company::query()->orderBy('name')->pluck('name', 'id'))
                                ->searchable()
                                ->required(),
                        ])
                        ->action(function (Collection $records, array $data): void {
                            $records->each(fn (Domain $domain) => $domain
                                ->forceFill(['company_id' => $data['company_id']])
                                ->save());

                            Notification::make()
                                ->title(__('Domains assigned'))
                                ->body(__(':count domain(s) now belong to this customer.', ['count' => $records->count()]))
                                ->success()
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
