<?php

namespace App\Filament\Resources\Contracts\Pages;

use App\Filament\Resources\Contracts\ContractResource;
use App\Support\CsvExporter;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListContracts extends ListRecords
{
    protected static string $resource = ContractResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('export')
                ->label(__('Export CSV'))
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->action(function () {
                    $rows = $this->getFilteredTableQuery()
                        ->with(['company', 'vendor'])
                        ->get()
                        ->map(fn ($c) => [
                            $c->id,
                            $c->company?->name,
                            $c->name,
                            $c->type?->getLabel() ?? $c->type,
                            $c->status?->getLabel() ?? $c->status,
                            $c->billing_cycle?->getLabel() ?? $c->billing_cycle,
                            $c->quantity,
                            number_format((float) $c->sale_price, 2),
                            number_format((float) $c->total_sale, 2),
                            number_format((float) $c->margin_eur, 2),
                            $c->renewal_date?->format('Y-m-d'),
                            $c->notice_deadline?->format('Y-m-d'),
                            $c->auto_renew ? 'yes' : 'no',
                            $c->stripePaymentStatusLabel(),
                            $c->notify_renewals ? 'yes' : 'no',
                        ]);

                    return CsvExporter::download('contracts.csv', [
                        'id', 'customer', 'service', 'type', 'status', 'billing',
                        'quantity', 'sale_unit', 'sale_total', 'margin_eur',
                        'renewal_date', 'notice_deadline', 'auto_renew', 'collection', 'customer_email',
                    ], $rows);
                }),
            CreateAction::make(),
        ];
    }
}
