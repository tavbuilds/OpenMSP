<?php

namespace App\Filament\Resources\Endpoints\Tables;

use App\Enums\EndpointKind;
use App\Enums\EndpointSource;
use App\Filament\Resources\Endpoints\EndpointResource;
use App\Models\Endpoint;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class EndpointsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('expires_at', 'asc')
            ->recordUrl(fn (Endpoint $record) => EndpointResource::getUrl('view', ['record' => $record]))
            ->columns([
                TextColumn::make('name')->label(__('Name'))->searchable()->weight('bold'),
                TextColumn::make('company.name')->label(__('Customer'))->placeholder(__('Internal'))->searchable()->visibleFrom('md'),
                TextColumn::make('kind')->label(__('Type'))->badge()->visibleFrom('lg'),
                TextColumn::make('hostname')->label(__('Host'))->placeholder(__('—'))->toggleable()->visibleFrom('lg'),
                TextColumn::make('expires_at')->label(__('Expires'))->date('M j, Y')->sortable()->placeholder(__('—')),
                TextColumn::make('days')
                    ->label(__('Days'))
                    ->state(fn (Endpoint $record) => $record->daysUntilExpiry())
                    ->badge()
                    ->color(fn (Endpoint $record) => $record->statusColor()),
                TextColumn::make('last_status')
                    ->label(__('Status'))
                    ->badge()
                    ->formatStateUsing(fn (Endpoint $record) => $record->statusLabel())
                    ->color(fn (Endpoint $record) => $record->statusColor())
                    ->visibleFrom('md'),
                TextColumn::make('source')->label(__('Source'))->badge()->visibleFrom('xl'),
            ])
            ->filters([
                SelectFilter::make('kind')->label(__('Type'))->options(EndpointKind::class),
                SelectFilter::make('source')->label(__('Source'))->options(EndpointSource::class),
                SelectFilter::make('company_id')->label(__('Customer'))->relationship('company', 'name')->searchable()->preload(),
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
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
