<?php

namespace App\Filament\Resources\Contracts\Pages;

use App\Filament\Resources\Contracts\ContractResource;
use App\Filament\Resources\Contracts\Schemas\ContractForm;
use App\Support\Breakpoints;
use Filament\Resources\Pages\CreateRecord;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Support\Icons\Heroicon;

class CreateContract extends CreateRecord
{
    use CreateRecord\Concerns\HasWizard;

    protected static string $resource = ContractResource::class;

    protected function getSteps(): array
    {
        return [
            Step::make('Customer & service')
                ->icon(Heroicon::OutlinedBuildingOffice2)
                ->description(__('Company, vendor, and service'))
                ->columns(Breakpoints::TWO)
                ->schema(ContractForm::customerServiceComponents()),

            Step::make('Price & quantity')
                ->icon(Heroicon::OutlinedCurrencyEuro)
                ->description(__('Quantity, cost, sale, and margin'))
                ->columns(Breakpoints::THREE)
                ->schema(ContractForm::prijsQuantityComponents()),

            Step::make('Term & renewal')
                ->icon(Heroicon::OutlinedCalendarDays)
                ->description(__('Start, renewal, and notice period'))
                ->columns(Breakpoints::THREE)
                ->schema(ContractForm::looptijdComponents()),

            Step::make('License keys & notes')
                ->icon(Heroicon::OutlinedKey)
                ->description(__('Optional'))
                ->schema(ContractForm::licentiesComponents()),
        ];
    }
}
