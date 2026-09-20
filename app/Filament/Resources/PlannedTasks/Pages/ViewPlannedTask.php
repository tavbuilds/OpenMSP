<?php

namespace App\Filament\Resources\PlannedTasks\Pages;

use App\Filament\Resources\PlannedTasks\PlannedTaskResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewPlannedTask extends ViewRecord
{
    protected static string $resource = PlannedTaskResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
