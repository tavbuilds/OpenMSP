<?php

namespace App\Filament\Resources\Contracts\Pages;

use App\Filament\Resources\Contracts\ContractResource;
use App\Filament\Resources\Contracts\Schemas\ContractForm;
use App\Models\Contract;
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

    /**
     * Arriving from "Add service" on a collection: the new service joins it
     * and inherits its term, so the term step would only offer dates the
     * collection is about to overwrite.
     */
    public function mount(): void
    {
        parent::mount();

        $this->parentGroup = $this->resolveParentGroup();

        if ($this->parentGroup !== null) {
            $term = ['company_id' => $this->parentGroup->company_id];
            foreach (Contract::GROUP_LED_FIELDS as $field) {
                $term[$field] = $this->parentGroup->getAttribute($field);
            }

            $this->form->fill([...$this->data, ...$term]);
        }
    }

    public ?Contract $parentGroup = null;

    private function resolveParentGroup(): ?Contract
    {
        $id = request()->query('parent');

        if (! is_string($id) && ! is_int($id)) {
            return null;
        }

        $group = Contract::query()->whereKey($id)->first();

        return $group?->isGroup() ? $group : null;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if ($this->parentGroup !== null) {
            $data['parent_id'] = $this->parentGroup->getKey();
            $data['company_id'] = $this->parentGroup->company_id;
            foreach (Contract::GROUP_LED_FIELDS as $field) {
                $data[$field] = $this->parentGroup->getAttribute($field);
            }
        }

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->parentGroup !== null
            ? ContractResource::getUrl('view', ['record' => $this->parentGroup])
            : parent::getRedirectUrl();
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
