<?php

namespace App\Filament\Resources\PlannedTasks\Pages;

use App\Filament\Resources\PlannedTasks\PlannedTaskResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditPlannedTask extends EditRecord
{
    protected static string $resource = PlannedTaskResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
