<?php

namespace App\Filament\Resources\PurchaseBundles\Schemas;

use App\Models\PurchaseBundle;
use App\Support\Breakpoints;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class PurchaseBundleInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Allocation')
                    ->description(__('Total cost is split equally across linked active contracts.'))
                    ->columns(Breakpoints::FOUR)
                    ->schema([
                        TextEntry::make('total_cost')->label(__('Total cost'))->money('EUR'),
                        TextEntry::make('active_contracts')
                            ->label(__('Active contracts'))
                            ->state(fn (PurchaseBundle $record) => $record->activeContractsCount()),
                        TextEntry::make('allocated')
                            ->label(__('Cost per contract (per year)'))
                            ->state(fn (PurchaseBundle $record) => '€ ' . number_format($record->allocatedAnnualCostPerContract(), 2)),
                        TextEntry::make('recovery')
                            ->label(__('Covered by resale'))
                            ->state(fn (PurchaseBundle $record) => $record->recoveryPercentage() . '%')
                            ->badge()
                            ->color(fn (PurchaseBundle $record) => $record->recoveryPercentage() >= 100 ? 'success' : ($record->recoveryPercentage() >= 60 ? 'warning' : 'danger')),
                    ]),

                Section::make('Bundle details')
                    ->columns(Breakpoints::THREE)
                    ->schema([
                        TextEntry::make('name')->label(__('Bundle name')),
                        TextEntry::make('vendor.name')->label(__('Vendor'))->placeholder(__('—')),
                        TextEntry::make('reference')->label(__('Reference'))->placeholder(__('—')),
                        TextEntry::make('billing_cycle')->label(__('Billing cycle'))->badge(),
                        TextEntry::make('start_date')->label(__('Start date'))->date('M j, Y')->placeholder(__('—')),
                        TextEntry::make('renewal_date')->label(__('Renewal / end date'))->date('M j, Y')->placeholder(__('—')),
                        TextEntry::make('annual_resale')
                            ->label(__('Resale revenue (per year)'))
                            ->state(fn (PurchaseBundle $record) => '€ ' . number_format($record->annualResale(), 2))
                            ->color('success'),
                    ]),
            ]);
    }
}
