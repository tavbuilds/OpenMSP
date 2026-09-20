<?php

namespace App\Filament\Resources\PlannedTasks\Pages;

use App\Filament\Resources\PlannedTasks\PlannedTaskResource;
use App\Support\CsvExporter;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPlannedTasks extends ListRecords
{
    protected static string $resource = PlannedTaskResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('export')
                ->label(__('Export CSV'))
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->action(function () {
                    $rows = $this->getFilteredTableQuery()
                        ->with(['company', 'assignedUser'])
                        ->get()
                        ->map(fn ($task) => [
                            $task->id,
                            $task->title,
                            $task->company?->name,
                            $task->kind?->getLabel(),
                            $task->status?->getLabel(),
                            $task->priority?->getLabel(),
                            $task->due_on?->format('Y-m-d'),
                            $task->daysUntilDue(),
                            $task->assignedUser?->name,
                            $task->location_from,
                            $task->location_to,
                        ]);

                    return CsvExporter::download('planned-tasks.csv', [
                        'id', 'title', 'customer', 'kind', 'status', 'priority',
                        'due_on', 'days', 'assignee', 'from', 'to',
                    ], $rows);
                }),
            CreateAction::make(),
        ];
    }
}
