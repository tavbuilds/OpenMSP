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
use Illuminate\Database\Eloquent\Collection;

class DomainsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('expires_at', 'asc')
            ->recordUrl(fn (Domain $record) => DomainResource::getUrl('view', ['record' => $record]))
            ->columns([
                TextColumn::make('name')->label(__('Domain name'))->searchable()->weight('bold'),
                TextColumn::make('company.name')
                    ->label(__('Customer'))
                    ->placeholder(__('Not assigned'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('expires_at')->label(__('Expires'))->date('M j, Y')->sortable()->placeholder(__('—')),
                TextColumn::make('days')
                    ->label(__('Days'))
                    ->state(fn (Domain $record) => $record->daysUntilExpiry())
                    ->badge()
                    ->color(fn (Domain $record) => $record->statusColor()),
                IconColumn::make('auto_renew')
                    ->label(__('Auto-renew'))
                    ->boolean()
                    ->visibleFrom(Breakpoints::COLUMN_SECONDARY),
                TextColumn::make('cost')
                    ->label(__('Purchase / year'))
                    ->alignEnd()
                    ->state(fn (Domain $record) => $record->costPrice() !== null
                        ? Money::format($record->costPrice(), $record->product?->currency)
                        : null)
                    ->placeholder(__('—'))
                    ->visibleFrom(Breakpoints::COLUMN_TERTIARY),
                TextColumn::make('extension')
                    ->label(__('Extension'))
                    ->prefix('.')
                    ->toggleable()
                    ->visibleFrom(Breakpoints::COLUMN_WIDEST),
                TextColumn::make('source')->label(__('Source'))->badge()->visibleFrom(Breakpoints::COLUMN_WIDEST),
            ])
            ->filters([
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
                        true: fn ($q) => $q->whereDate('expires_at', '<', now()->toDateString()),
                        false: fn ($q) => $q->whereDate('expires_at', '>=', now()->toDateString()),
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
