<?php

namespace App\Filament\Resources\PlannedTasks\Tables;

use App\Enums\PlannedTaskKind;
use App\Enums\PlannedTaskPriority;
use App\Enums\PlannedTaskStatus;
use App\Filament\Resources\PlannedTasks\PlannedTaskResource;
use App\Models\PlannedTask;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class PlannedTasksTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('due_on', 'asc')
            ->recordUrl(fn (PlannedTask $record) => PlannedTaskResource::getUrl('view', ['record' => $record]))
            ->columns([
                TextColumn::make('title')->label(__('Title'))->searchable()->weight('bold'),
                TextColumn::make('company.name')->label(__('Customer'))->placeholder(__('Internal'))->searchable()->visibleFrom('md'),
                TextColumn::make('kind')->label(__('Type'))->badge()->visibleFrom('lg'),
                TextColumn::make('status')->label(__('Status'))->badge(),
                TextColumn::make('priority')->label(__('Priority'))->badge()->visibleFrom('md'),
                TextColumn::make('due_on')->label(__('Deadline'))->date('M j, Y')->sortable(),
                TextColumn::make('days')
                    ->label(__('Days'))
                    ->state(fn (PlannedTask $record) => $record->daysUntilDue())
                    ->badge()
                    ->color(fn (PlannedTask $record) => $record->dueColor()),
                TextColumn::make('assignedUser.name')->label(__('Assignee'))->placeholder(__('Unassigned'))->visibleFrom('xl'),
            ])
            ->filters([
                SelectFilter::make('kind')->label(__('Type'))->options(PlannedTaskKind::class),
                SelectFilter::make('status')->label(__('Status'))->options(PlannedTaskStatus::class),
                SelectFilter::make('priority')->label(__('Priority'))->options(PlannedTaskPriority::class),
                SelectFilter::make('company_id')->label(__('Customer'))->relationship('company', 'name')->searchable()->preload(),
                SelectFilter::make('assigned_user_id')->label(__('Assignee'))->relationship('assignedUser', 'name')->searchable()->preload(),
                TernaryFilter::make('open')
                    ->label(__('Open'))
                    ->queries(
                        true: fn ($q) => $q->open(),
                        false: fn ($q) => $q->whereIn('status', [
                            PlannedTaskStatus::Done->value,
                            PlannedTaskStatus::Cancelled->value,
                        ]),
                    ),
                TernaryFilter::make('overdue')
                    ->label(__('Overdue'))
                    ->queries(
                        true: fn ($q) => $q->open()->whereDate('due_on', '<', now()->toDateString()),
                        false: fn ($q) => $q->whereDate('due_on', '>=', now()->toDateString()),
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
