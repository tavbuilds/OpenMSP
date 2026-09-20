<?php

namespace App\Filament\Resources\Endpoints\Pages;

use App\Filament\Resources\Endpoints\EndpointResource;
use App\Models\Endpoint;
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
            Action::make('rotateToken')
                ->label(__('Rotate webhook token'))
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading(__('Create a new webhook URL?'))
                ->modalDescription(__('The old URL stops immediately. Paste the new URL into Uptime Kuma or your monitor.'))
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
