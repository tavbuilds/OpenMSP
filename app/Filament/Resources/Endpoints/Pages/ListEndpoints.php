<?php

namespace App\Filament\Resources\Endpoints\Pages;

use App\Filament\Resources\Endpoints\EndpointResource;
use App\Support\CsvExporter;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListEndpoints extends ListRecords
{
    protected static string $resource = EndpointResource::class;

    protected function getHeaderActions(): array
    {
        return [
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
