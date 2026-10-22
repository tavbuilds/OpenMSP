<?php

namespace App\Filament\Resources\Contracts\Pages;

use App\Filament\Resources\Contracts\ContractResource;
use App\Filament\Resources\Contracts\Pages\Concerns\InteractsWithContractLifecycle;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewContract extends ViewRecord
{
    use InteractsWithContractLifecycle;

    protected static string $resource = ContractResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ...$this->lifecycleActions(),
            EditAction::make(),
        ];
    }
}
