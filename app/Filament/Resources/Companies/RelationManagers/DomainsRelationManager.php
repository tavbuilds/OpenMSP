<?php

namespace App\Filament\Resources\Companies\RelationManagers;

use App\Filament\Resources\Domains\DomainResource;
use App\Models\Domain;
use Filament\Actions\CreateAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class DomainsRelationManager extends RelationManager
{
    protected static string $relationship = 'domains';

    protected static ?string $title = 'Domains';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('Domains');
    }

    public function isReadOnly(): bool
    {
        return ! (auth()->user()?->canManageContracts() ?? false);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->defaultSort(fn (Builder $query, string $direction) => $query->orderByDue($direction))
            // Not a filter here: this tab answers "what does this customer
            // have", and what they no longer have belongs on the domain list.
            ->modifyQueryUsing(fn (Builder $query) => $query->present())
            ->recordUrl(fn ($record) => DomainResource::getUrl('view', ['record' => $record]))
            ->columns([
                TextColumn::make('name')->label(__('Domain name'))->weight('bold'),
                TextColumn::make('due')
                    ->label(__('Renews'))
                    ->state(fn (Domain $record) => $record->expiryDate())
                    ->date('M j, Y')
                    ->placeholder(__('—')),
                TextColumn::make('days')
                    ->label(__('Days'))
                    ->state(fn ($record) => $record->daysUntilExpiry())
                    ->badge()
                    ->color(fn ($record) => $record->statusColor()),
                IconColumn::make('auto_renew')->label(__('Auto-renew'))->boolean()->visibleFrom('md'),
                IconColumn::make('notes_visible_to_customer')
                    ->label(__('Note shared'))
                    ->boolean()
                    ->visibleFrom('lg'),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label(__('Add domain'))
                    ->url(fn () => DomainResource::getUrl('create')),
            ]);
    }
}
