<?php

namespace App\Filament\Resources\Companies\Pages;

use App\Filament\Resources\Companies\CompanyResource;
use App\Support\CsvExporter;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCompanies extends ListRecords
{
    protected static string $resource = CompanyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('export')
                ->label(__('Export CSV'))
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->action(function () {
                    $rows = $this->getFilteredTableQuery()
                        ->get()
                        ->map(fn ($c) => [
                            $c->id,
                            $c->name,
                            $c->city,
                            $c->email,
                            $c->phone,
                            $c->contracts()->count(),
                            number_format($c->mrr(), 2),
                            number_format($c->annualMargin(), 2),
                            $c->notify_renewals ? 'yes' : 'no',
                        ]);

                    return CsvExporter::download('customers.csv', [
                        'id', 'name', 'city', 'email', 'phone',
                        'contracts', 'mrr', 'annual_margin', 'customer_email',
                    ], $rows);
                }),
            CreateAction::make(),
        ];
    }
}
