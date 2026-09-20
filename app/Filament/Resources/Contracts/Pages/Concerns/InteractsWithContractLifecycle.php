<?php

namespace App\Filament\Resources\Contracts\Pages\Concerns;

use App\Models\Contract;
use App\Services\StripeBillingService;
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
                ->modalDescription(__('Status becomes Canceled and auto-renew is turned off. A linked Stripe subscription is stopped if present.'))
                ->visible(fn (): bool => $this->getRecord()->canCancel())
                ->action(function (): void {
                    /** @var Contract $record */
                    $record = $this->getRecord();
                    $record->cancelNow();
                    try {
                        app(StripeBillingService::class)->cancelSubscription($record->fresh());
                    } catch (\Throwable $e) {
                        report($e);
                    }
                    Notification::make()
                        ->title(__('Contract canceled'))
                        ->success()
                        ->send();
                }),
        ];
    }
}
