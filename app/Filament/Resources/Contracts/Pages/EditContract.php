<?php

namespace App\Filament\Resources\Contracts\Pages;

use App\Filament\Resources\Contracts\ContractResource;
use App\Filament\Resources\Contracts\Pages\Concerns\InteractsWithContractLifecycle;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditContract extends EditRecord
{
    use InteractsWithContractLifecycle;

    protected static string $resource = ContractResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ...$this->lifecycleActions(),
            DeleteAction::make(),
        ];
    }
}
