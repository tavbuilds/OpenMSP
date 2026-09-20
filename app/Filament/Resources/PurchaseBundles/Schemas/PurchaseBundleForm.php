<?php

namespace App\Filament\Resources\PurchaseBundles\Schemas;

use App\Enums\BillingCycle;
use App\Support\Breakpoints;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class PurchaseBundleForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Purchase bundle')
                    ->description(__('One purchased bundle with a fixed total cost. Cost is split equally across linked active customer contracts.'))
                    ->columns(Breakpoints::TWO)
                    ->schema([
                        TextInput::make('name')->label(__('Bundle name'))->required(),
                        TextInput::make('reference')->label(__('Reference / order number')),
                        Select::make('vendor_id')
                            ->label(__('Vendor'))
                            ->relationship('vendor', 'name')
                            ->searchable()
                            ->preload()
                            ->createOptionForm([
                                TextInput::make('name')->label(__('Name'))->required(),
                                TextInput::make('website'),
                            ]),
                    ]),

                Section::make('Cost & term')
                    ->columns(Breakpoints::THREE)
                    ->schema([
                        TextInput::make('total_cost')->label(__('Total cost'))->numeric()->prefix('€')->default(0)->required(),
                        Select::make('currency')->label(__('Currency'))->options(['EUR' => 'EUR', 'USD' => 'USD', 'GBP' => 'GBP'])->default('EUR')->required(),
                        Select::make('billing_cycle')->label(__('Billing cycle'))->options(BillingCycle::class)->default('yearly')->required(),
                        DatePicker::make('start_date')->label(__('Start date'))->default(now()),
                        DatePicker::make('renewal_date')->label(__('Renewal / end date')),
                    ]),

                Section::make('Notes')
                    ->collapsed()
                    ->schema([
                        Textarea::make('notes')->hiddenLabel()->columnSpanFull(),
                    ]),
            ]);
    }
}
