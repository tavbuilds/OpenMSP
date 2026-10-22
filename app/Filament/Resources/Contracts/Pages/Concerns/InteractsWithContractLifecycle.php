<?php

namespace App\Filament\Resources\Contracts\Pages\Concerns;

use App\Models\Contract;
use App\Support\ContractCancellation;
use App\Support\ContractTermination;
use Filament\Actions\Action;
use Filament\Notifications\Notification;

trait InteractsWithContractLifecycle
{
    /** @return array<int, Action> */
    protected function lifecycleActions(): array
    {
        return [
            Action::make('renew')
                ->label(__('Renew'))
                ->icon('heroicon-o-arrow-path')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading(__('Renew this contract?'))
                ->modalDescription(__('The renewal date (and invoice date, if set) moves forward one billing period. Status becomes Active.'))
                ->visible(fn (): bool => $this->getRecord()->canRenew())
                ->action(function (): void {
                    /** @var Contract $record */
                    $record = $this->getRecord();
                    $record->renewPeriod();
                    Notification::make()
                        ->title(__('Contract renewed'))
                        ->body('New renewal date: '.($record->fresh()->renewal_date?->format('M j, Y') ?? '—'))
                        ->success()
                        ->send();
                }),
            Action::make('cancelContract')
                ->label(__('Cancel'))
                ->icon('heroicon-o-no-symbol')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading(__('Cancel this contract?'))
                ->modalDescription(function (): string {
                    /** @var Contract $record */
                    $record = $this->getRecord();
                    $cancellation = ContractCancellation::for($record);

                    $lines = [__('Auto-renew goes off and any direct debit for it is stopped.')];

                    if ($record->isGroup() && $record->services()->exists()) {
                        $lines[] = __('Everything in this contract is cancelled with it.');
                    }

                    if ($ends = $cancellation->endsOn()) {
                        $lines[] = __('The service runs until :date.', ['date' => $ends->translatedFormat('j F Y')]);
                    }

                    if ($invoice = $cancellation->lastInvoiceOn()) {
                        $lines[] = __('The last invoice is on :date.', ['date' => $invoice->translatedFormat('j F Y')]);
                    }

                    $lines[] = __('A task is raised so the licence, hosting or domain behind it is not renewed.');

                    return implode(' ', $lines);
                })
                ->visible(fn (): bool => $this->getRecord()->canCancel())
                ->action(function (): void {
                    /** @var Contract $record */
                    $record = $this->getRecord();

                    // One path for every cancellation, whoever started it:
                    // Stripe stopped, task raised, staff told.
                    ContractTermination::cancel($record, by: 'admin');

                    Notification::make()
                        ->title(__('Contract canceled'))
                        ->body(__('A task has been raised so the renewal can be stopped at the supplier.'))
                        ->success()
                        ->send();
                }),
        ];
    }
}
