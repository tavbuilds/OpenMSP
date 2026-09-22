<?php

namespace App\Filament\Resources\Vendors\Tables;

use App\Support\Breakpoints;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class VendorsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('website')
                    ->searchable()
                    ->visibleFrom(Breakpoints::COLUMN_SECONDARY),
                TextColumn::make('email')
                    ->label(__('Email address'))
                    ->searchable()
                    ->visibleFrom(Breakpoints::COLUMN_SECONDARY),
                TextColumn::make('phone')
                    ->searchable()
                    ->visibleFrom(Breakpoints::COLUMN_TERTIARY),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
