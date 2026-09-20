<?php

namespace App\Filament\Resources\Companies\RelationManagers;

use App\Filament\Resources\Endpoints\EndpointResource;
use Filament\Actions\CreateAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class EndpointsRelationManager extends RelationManager
{
    protected static string $relationship = 'endpoints';

    protected static ?string $title = 'Endpoints';

    public function isReadOnly(): bool
    {
        return ! (auth()->user()?->canManageContracts() ?? false);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->defaultSort('expires_at', 'asc')
            ->recordUrl(fn ($record) => EndpointResource::getUrl('view', ['record' => $record]))
            ->columns([
                TextColumn::make('name')->label(__('Name'))->weight('bold'),
                TextColumn::make('kind')->label(__('Type'))->badge()->visibleFrom('md'),
                TextColumn::make('hostname')->label(__('Host'))->placeholder(__('—'))->visibleFrom('lg'),
                TextColumn::make('expires_at')->label(__('Expires'))->date('M j, Y')->placeholder(__('—')),
                TextColumn::make('days')
                    ->label(__('Days'))
                    ->state(fn ($record) => $record->daysUntilExpiry())
                    ->badge()
                    ->color(fn ($record) => $record->statusColor()),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label(__('Add endpoint'))
                    ->url(fn () => EndpointResource::getUrl('create')),
            ]);
    }
}
