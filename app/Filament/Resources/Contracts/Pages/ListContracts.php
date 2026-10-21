<?php

namespace App\Filament\Resources\Contracts\Pages;

use App\Enums\BillingCycle;
use App\Enums\ContractStatus;
use App\Enums\ProductType;
use App\Filament\Resources\Contracts\ContractResource;
use App\Models\Company;
use App\Models\Contract;
use App\Support\CsvExporter;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
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
            // An empty collection you then fill, for the case where the
            // services do not exist yet. Combining existing ones is a bulk
            // action on the table below.
            Action::make('createGroup')
                ->label(__('New contract (collection)'))
                ->icon('heroicon-o-rectangle-stack')
                ->color('gray')
                ->visible(fn () => auth()->user()?->canManageContracts() ?? false)
                ->modalHeading(__('New contract (collection)'))
                ->modalDescription(__('A contract that holds several services. Add them afterwards; they keep their own prices.'))
                ->modalSubmitActionLabel(__('Create'))
                ->schema([
                    Select::make('company_id')
                        ->label(__('Company'))
                        ->options(fn () => Company::query()->orderBy('name')->pluck('name', 'id'))
                        ->searchable()
                        ->required(),
                    TextInput::make('name')
                        ->label(__('Contract name'))
                        ->placeholder(__('Website hosting & domains'))
                        ->required()
                        ->maxLength(120),
                    Select::make('billing_cycle')
                        ->label(__('Billing cycle'))
                        ->options(BillingCycle::class)
                        ->default(BillingCycle::Yearly->value)
                        ->required(),
                    DatePicker::make('start_date')->label(__('Start date'))->default(now()),
                    DatePicker::make('renewal_date')->label(__('Renewal date')),
                    TextInput::make('notice_period_days')
                        ->label(__('Notice period (days)'))
                        ->numeric()
                        ->default(0)
                        ->minValue(0),
                    Toggle::make('auto_renew')->label(__('Renews automatically'))->default(true),
                ])
                ->action(function (array $data) {
                    $group = Contract::query()->create([
                        ...$data,
                        'is_group' => true,
                        'type' => ProductType::Service,
                        'quantity' => 1,
                        'cost_price' => null,
                        'sale_price' => null,
                        'currency' => 'EUR',
                        'status' => ContractStatus::Active,
                        'notify_renewals' => true,
                    ]);

                    return redirect(ContractResource::getUrl('view', ['record' => $group]));
                }),

            CreateAction::make(),
        ];
    }
}
