<?php

namespace App\Filament\Resources\Endpoints\Pages;

use App\Filament\Resources\Endpoints\EndpointResource;
use App\Models\Endpoint;
use App\Monitoring\UptimeKuma\UptimeKumaException;
use App\Monitoring\UptimeKuma\UptimeKumaWebhooks;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewEndpoint extends ViewRecord
{
    protected static string $resource = EndpointResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('pushKuma')
                ->label(__('Add to Uptime Kuma'))
                ->requiresConfirmation()
                ->modalHeading(__('Create the monitor and webhook in Uptime Kuma?'))
                ->modalDescription(__('OpenMSP creates an HTTPS monitor when Uptime Kuma does not have one for this endpoint, attaches the JSON webhook and turns certificate and domain expiry notifications off. Other channels stay as they are.'))
                ->visible(fn (): bool => auth()->user()?->canAdminister() ?? false)
                ->action(function (): void {
                    /** @var Endpoint $record */
                    $record = $this->getRecord();

                    try {
                        $report = app(UptimeKumaWebhooks::class)->push($record->id);
                        Notification::make()
                            ->title(__('Webhook URLs sent to Uptime Kuma'))
                            ->body(UptimeKumaWebhooks::summary($report))
                            ->success()
                            ->send();
                    } catch (UptimeKumaException $e) {
                        Notification::make()
                            ->title(__('Could not add the webhook URLs'))
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),
            Action::make('rotateToken')
                ->label(__('Rotate webhook token'))
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading(__('Create a new webhook URL?'))
                ->modalDescription(__('The old URL stops immediately. Add to Uptime Kuma again so the monitor uses the new URL.'))
                ->visible(fn (): bool => auth()->user()?->canManageContracts() ?? false)
                ->action(function (): void {
                    /** @var Endpoint $record */
                    $record = $this->getRecord();
                    $record->rotateWebhookToken();
                    Notification::make()
                        ->title(__('Token rotated'))
                        ->body('Copy the new URL below.')
                        ->success()
                        ->send();
                }),
            EditAction::make(),
        ];
    }
}
