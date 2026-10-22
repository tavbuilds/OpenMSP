<?php

namespace App\Filament\Resources\Contracts\Pages;

use App\Filament\Resources\Contracts\ContractResource;
use App\Filament\Resources\Contracts\Pages\Concerns\InteractsWithContractLifecycle;
use App\Models\Contract;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewContract extends ViewRecord
{
    use InteractsWithContractLifecycle;

    protected static string $resource = ContractResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->withdrawChangeAction(),
            ...$this->lifecycleActions(),
            EditAction::make(),
        ];
    }

    /**
     * Takes back a change the customer has not answered.
     *
     * Without this a request sits there forever: only the customer can
     * approve it, only one can be outstanding at a time, and until it is
     * gone no further move can be proposed for this contract.
     */
    private function withdrawChangeAction(): Action
    {
        return Action::make('withdrawBillingChange')
            ->label(__('Withdraw the request'))
            ->icon('heroicon-o-arrow-uturn-left')
            ->color('gray')
            ->requiresConfirmation()
            ->modalHeading(__('Withdraw the change waiting on the customer?'))
            ->modalDescription(__('Nothing was applied, so nothing is undone. The customer can no longer approve it, and you are free to propose another change.'))
            ->visible(function (): bool {
                /** @var Contract $record */
                $record = $this->getRecord();

                return $record->pendingBillingChange() !== null
                    && (auth()->user()?->canManageContracts() ?? false);
            })
            ->action(function (): void {
                /** @var Contract $record */
                $record = $this->getRecord();
                $record->billingChanges()->pending()->update(['status' => 'withdrawn']);

                Notification::make()
                    ->title(__('Request withdrawn'))
                    ->body(__('The contract is unchanged.'))
                    ->success()
                    ->send();
            });
    }
}
