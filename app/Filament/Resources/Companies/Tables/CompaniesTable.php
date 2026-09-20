<?php

namespace App\Filament\Resources\Companies\Tables;

use App\Enums\ContractStatus;
use App\Filament\Resources\Companies\CompanyResource;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CompaniesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->recordUrl(fn ($record) => CompanyResource::getUrl('view', ['record' => $record]))
            ->columns([
                TextColumn::make('name')->label(__('Company name'))->searchable()->sortable()->weight('bold'),
                TextColumn::make('city')->label(__('City'))->searchable()->visibleFrom('md'),
                TextColumn::make('email')->label(__('Email'))->searchable()->toggleable()->visibleFrom('lg'),
                TextColumn::make('phone')->label(__('Phone'))->searchable()->toggleable()->visibleFrom('xl'),
                TextColumn::make('contracts_count')
                    ->label(__('Active contracts'))
                    ->counts(['contracts' => fn ($query) => $query->where('status', ContractStatus::Active->value)])
                    ->badge()
                    ->alignEnd(),
                TextColumn::make('mrr')
                    ->label(__('MRR'))
                    ->state(fn ($record) => '€ ' . number_format($record->mrr(), 2))
                    ->alignEnd()
                    ->visibleFrom('md'),
                TextColumn::make('annual_margin')
                    ->label(__('Annual margin'))
                    ->state(fn ($record) => '€ ' . number_format($record->annualMargin(), 2))
                    ->color('success')
                    ->alignEnd()
                    ->visibleFrom('lg'),
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
