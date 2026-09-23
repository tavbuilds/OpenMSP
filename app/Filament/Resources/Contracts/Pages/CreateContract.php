<?php

namespace App\Filament\Resources\Contracts\Pages;

use App\Filament\Resources\Contracts\ContractResource;
use App\Filament\Resources\Contracts\Schemas\ContractForm;
use App\Support\Breakpoints;
use Filament\Resources\Pages\CreateRecord;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Wizard;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Support\Icons\Heroicon;

class CreateContract extends CreateRecord
{
    use CreateRecord\Concerns\HasWizard {
        getWizardComponent as baseWizardComponent;
    }

    protected static string $resource = ContractResource::class;

    /**
     * Filament leaves the wizard uncontained, which puts the step header on a
     * card and the fields under it on the bare page. Every other create page
     * in the panel is a card, so this one is too.
     */
    public function getWizardComponent(): Component
    {
        /** @var Wizard $wizard */
        $wizard = $this->baseWizardComponent();

        return $wizard->contained();
    }

    protected function getSteps(): array
    {
        return [
            Step::make(__('Customer & service'))
                ->icon(Heroicon::OutlinedBuildingOffice2)
                ->description(__('Company, vendor, and service'))
                ->columns(Breakpoints::TWO)
                ->schema(ContractForm::customerServiceComponents()),

            Step::make(__('Price & quantity'))
                ->icon(Heroicon::OutlinedCurrencyEuro)
                ->description(__('Quantity, cost, sale, and margin'))
                ->columns(Breakpoints::THREE)
                ->schema(ContractForm::prijsQuantityComponents()),

            Step::make(__('Term & renewal'))
                ->icon(Heroicon::OutlinedCalendarDays)
                ->description(__('Start, renewal, and notice period'))
                ->columns(Breakpoints::THREE)
                ->schema(ContractForm::looptijdComponents()),

            Step::make(__('License keys & notes'))
                ->icon(Heroicon::OutlinedKey)
                ->description(__('Optional'))
                ->schema(ContractForm::licentiesComponents()),
        ];
    }
}
