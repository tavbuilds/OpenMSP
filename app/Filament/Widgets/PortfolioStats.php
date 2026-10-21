<?php

namespace App\Filament\Widgets;

use App\Enums\ContractStatus;
use App\Models\Contract;
use App\Support\Money;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class PortfolioStats extends BaseWidget
{
    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    protected function getColumns(): int
    {
        return 4;
    }

    protected function getStats(): array
    {
        $active = Contract::where('status', ContractStatus::Active->value)->get();

        // Money comes off the services: a collection has no price of its
        // own, so counting both would be right only by accident.
        $billable = $active->filter(fn (Contract $c) => ! $c->isGroup());
        $arr = $billable->sum(fn (Contract $c) => $c->annual_revenue);
        $annualMargin = $billable->sum(fn (Contract $c) => $c->annual_margin);
        $mrr = $arr / 12;

        // ...but the count is of contracts as an operator means them: a
        // collection is one contract, not one plus everything in it.
        $activeCount = $active->filter(fn (Contract $c) => ! $c->isInGroup())->count();

        $upcoming = Contract::query()
            ->withUpcomingRenewalTerm()
            ->whereBetween('renewal_date', [now(), now()->addDays(30)])
            ->count();

        return [
            Stat::make(__('Active contracts'), $activeCount)
                ->description(__('Active licenses & contracts'))
                ->color('primary'),

            Stat::make(__('MRR'), Money::formatWhole($mrr))
                ->description(__('ARR').': '.Money::formatWhole($arr))
                ->color('success'),

            Stat::make(__('Annual margin'), Money::formatWhole($annualMargin))
                ->description(__('Annualized margin'))
                ->color('success'),

            Stat::make(__('Renews in < 30 days'), $upcoming)
                ->description(__('Needs attention'))
                ->color($upcoming > 0 ? 'warning' : 'gray'),
        ];
    }
}
