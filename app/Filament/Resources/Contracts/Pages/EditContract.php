<?php

namespace App\Filament\Resources\Contracts\Pages;

use App\Filament\Resources\Contracts\ContractResource;
use App\Filament\Resources\Contracts\Pages\Concerns\InteractsWithContractLifecycle;
use App\Models\Contract;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
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

    /**
     * A collection owns the term, so changing it here has to reach the
     * services inside — otherwise the contract says one thing and the
     * renewal reminders another.
     */
    protected function afterSave(): void
    {
        $record = $this->getRecord();

        if ($record instanceof Contract && $record->isGroup()) {
            $changed = $record->syncTermToServices();

            if ($changed > 0) {
                Notification::make()
                    ->title(__('Services updated'))
                    ->body(trans_choice(
                        '{1} :count service in this contract now follows the new term.|[2,*] :count services in this contract now follow the new term.',
                        $changed,
                        ['count' => $changed],
                    ))
                    ->success()
                    ->send();
            }
        }
    }
}
