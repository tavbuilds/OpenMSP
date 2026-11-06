<?php

namespace App\Filament\Resources\Endpoints\Pages;

use App\Filament\Resources\Endpoints\EndpointResource;
use App\Models\User;
use App\Monitoring\UptimeKuma\UptimeKumaException;
use App\Monitoring\UptimeKuma\UptimeKumaWebhooks;
use App\Support\CsvExporter;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListEndpoints extends ListRecords
{
    protected static string $resource = EndpointResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('retryFailed')
                ->label(__('Submit failed domains again'))
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading(__('Submit the failed domains to Uptime Kuma again?'))
                ->modalDescription(__('OpenMSP sends only the domains whose last push to Uptime Kuma failed. Each domain is tried up to 3 times.'))
                ->visible(fn (): bool => auth()->user() instanceof User && auth()->user()->canAdminister())
                ->action(function (): void {
                    try {
                        $report = app(UptimeKumaWebhooks::class)->push(failedOnly: true);
                    } catch (UptimeKumaException $e) {
                        Notification::make()
                            ->title(__('Could not add the webhook URLs'))
                            ->body($e->getMessage())
                            ->danger()
                            ->send();

                        return;
                    }

                    if ($report['endpoints'] === 0) {
                        Notification::make()
                            ->title(__('No domains are waiting for another try.'))
                            ->info()
                            ->send();

                        return;
                    }

                    $notification = Notification::make()
                        ->title(__('Webhook URLs sent to Uptime Kuma'))
                        ->body(UptimeKumaWebhooks::summary($report));
                    ($report['failed_count'] > 0 ? $notification->warning() : $notification->success())->send();
                }),
            Action::make('export')
                ->label(__('Export CSV'))
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->action(function () {
                    $rows = $this->getFilteredTableQuery()
                        ->with('company')
                        ->get()
                        ->map(fn ($e) => [
                            $e->id,
                            $e->name,
                            $e->company?->name,
                            $e->kind?->getLabel(),
                            $e->hostname,
                            $e->expires_at?->format('Y-m-d'),
                            $e->daysUntilExpiry(),
                            $e->statusLabel(),
                            $e->source?->getLabel(),
                        ]);

                    return CsvExporter::download('endpoints.csv', [
                        'id', 'name', 'customer', 'kind', 'hostname',
                        'expires_at', 'days', 'status', 'source',
                    ], $rows);
                }),
            CreateAction::make(),
        ];
    }
}
