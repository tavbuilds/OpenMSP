<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Contracts\ContractResource;
use App\Models\Contract;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;

class UpcomingRenewals extends BaseWidget
{
    protected static ?int $sort = 5;

    public function getTableHeading(): ?string
    {
        return __('Upcoming renewals');
    }

    public function getTableDescription(): ?string
    {
        return __('Quarterly and yearly contracts only — a monthly contract can be cancelled every month.');
    }

    protected int|string|array $columnSpan = 'full';

    protected function getTableQuery(): Builder
    {
        return Contract::query()
            ->with('company')
            ->withUpcomingRenewalTerm()
            ->whereBetween('renewal_date', [now(), now()->addDays(60)])
            ->orderBy('renewal_date');
    }

    public function getTableRecordUrlUsing(): ?\Closure
    {
        return fn (Contract $record) => ContractResource::getUrl('view', ['record' => $record]);
    }

    protected function getTableColumns(): array
    {
        return [
            TextColumn::make('company.name')->label(__('Company'))->weight('bold'),
            TextColumn::make('name')->label(__('Service / package'))->visibleFrom('md'),
            TextColumn::make('renewal_date')->label(__('Renewal'))->date('M j, Y')->sortable(),
            TextColumn::make('total_sale')->label(__('Sale'))->money('EUR')->alignEnd()->visibleFrom('md'),
            IconColumn::make('auto_renew')->label(__('Auto-renew'))->boolean()->visibleFrom('lg'),
        ];
    }
}
