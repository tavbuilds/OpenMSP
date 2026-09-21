<?php

namespace App\Filament\Resources\Companies\RelationManagers;

use App\Filament\Resources\PlannedTasks\PlannedTaskResource;
use Filament\Actions\CreateAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PlannedTasksRelationManager extends RelationManager
{
    protected static string $relationship = 'plannedTasks';

    protected static ?string $title = 'Planning';

    public function isReadOnly(): bool
    {
        return ! (auth()->user()?->canManageContracts() ?? false);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('title')
            ->defaultSort('due_on', 'asc')
            ->recordUrl(fn ($record) => PlannedTaskResource::getUrl('view', ['record' => $record]))
            ->columns([
                TextColumn::make('title')->label(__('Title'))->weight('bold'),
                TextColumn::make('kind')->label(__('Type'))->badge()->visibleFrom('md'),
                TextColumn::make('status')->label(__('Status'))->badge(),
                TextColumn::make('due_on')->label(__('Deadline'))->date('M j, Y'),
                TextColumn::make('days')
                    ->label(__('Days'))
                    ->state(fn ($record) => $record->daysUntilDue())
                    ->badge()
                    ->color(fn ($record) => $record->dueColor()),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label(__('Add planned task'))
                    ->url(fn () => PlannedTaskResource::getUrl('create', [
                        'company_id' => $this->getOwnerRecord()->getKey(),
                    ])),
            ]);
    }
}
