<?php

namespace App\Filament\Resources\Companies\RelationManagers;

use App\Filament\Resources\Domains\DomainResource;
use Filament\Actions\CreateAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
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
            ->defaultSort('expires_at', 'asc')
            ->recordUrl(fn ($record) => DomainResource::getUrl('view', ['record' => $record]))
            ->columns([
                TextColumn::make('name')->label(__('Domain name'))->weight('bold'),
                TextColumn::make('expires_at')->label(__('Expires'))->date('M j, Y')->placeholder(__('—')),
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
