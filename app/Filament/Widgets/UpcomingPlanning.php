<?php

namespace App\Filament\Widgets;

use App\Enums\PlannedTaskPriority;
use App\Filament\Resources\PlannedTasks\PlannedTaskResource;
use App\Models\PlannedTask;
use Filament\Tables\Columns\TextColumn;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;

class UpcomingPlanning extends BaseWidget
{
    protected static ?string $heading = 'Upcoming planning (60 days)';

    public function getHeading(): ?string
    {
        return __('Upcoming planning (60 days)');
    }

    protected static ?int $sort = 6;

    protected int|string|array $columnSpan = 'full';

    protected function getTableQuery(): Builder
    {
        $urgent = PlannedTaskPriority::Urgent->value;
        $high = PlannedTaskPriority::High->value;
        $normal = PlannedTaskPriority::Normal->value;

        return PlannedTask::query()
            ->with('company')
            ->open()
            ->whereDate('due_on', '<=', now()->addDays(60)->toDateString())
            ->orderBy('due_on')
            ->orderByRaw(
                'CASE priority WHEN ? THEN 0 WHEN ? THEN 1 WHEN ? THEN 2 ELSE 3 END',
                [$urgent, $high, $normal]
            );
    }

    public function getTableRecordUrlUsing(): ?\Closure
    {
        return fn (PlannedTask $record) => PlannedTaskResource::getUrl('view', ['record' => $record]);
    }

    protected function getTableColumns(): array
    {
        return [
            TextColumn::make('title')->label(__('Title'))->weight('bold'),
            TextColumn::make('company.name')->label(__('Customer'))->placeholder(__('Internal'))->visibleFrom('md'),
            TextColumn::make('kind')->label(__('Type'))->badge()->visibleFrom('lg'),
            TextColumn::make('status')->label(__('Status'))->badge()->visibleFrom('md'),
            TextColumn::make('due_on')->label(__('Deadline'))->date('M j, Y'),
            TextColumn::make('days')
                ->label(__('Days'))
                ->state(fn (PlannedTask $record) => $record->daysUntilDue())
                ->badge()
                ->color(fn (PlannedTask $record) => $record->dueColor()),
        ];
    }
}
