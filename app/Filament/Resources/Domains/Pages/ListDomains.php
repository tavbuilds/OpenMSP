<?php

namespace App\Filament\Resources\Domains\Pages;

use App\Filament\Resources\Domains\DomainResource;
use App\Support\CsvExporter;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListDomains extends ListRecords
{
    protected static string $resource = DomainResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('export')
                ->label(__('Export CSV'))
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->action(function () {
                    $rows = $this->getFilteredTableQuery()
                        ->with(['company', 'product'])
                        ->get()
                        ->map(fn ($domain) => [
                            $domain->id,
                            $domain->name,
                            $domain->company?->name,
                            $domain->expires_at?->format('Y-m-d'),
                            $domain->daysUntilExpiry(),
                            $domain->auto_renew ? 'yes' : 'no',
                            $domain->costPrice(),
                            $domain->source?->getLabel(),
                        ]);

                    return CsvExporter::download('domains.csv', [
                        'id', 'domain', 'customer', 'expires_at', 'days',
                        'auto_renew', 'cost_per_year', 'source',
                    ], $rows);
                }),
            CreateAction::make(),
        ];
    }
}
