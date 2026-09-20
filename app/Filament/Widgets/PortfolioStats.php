<?php

namespace App\Filament\Widgets;

use App\Enums\ContractStatus;
use App\Models\Contract;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class PortfolioStats extends BaseWidget
{
    protected static ?int $sort = 1;

    protected int | string | array $columnSpan = 'full';

    protected function getColumns(): int
    {
        return 4;
    }

    protected function getStats(): array
    {
        $active = Contract::where('status', ContractStatus::Active->value)->get();

        $arr = $active->sum(fn (Contract $c) => $c->annual_revenue);
        $annualMargin = $active->sum(fn (Contract $c) => $c->annual_margin);
        $mrr = $arr / 12;

        $upcoming = Contract::where('status', ContractStatus::Active->value)
            ->whereNotNull('renewal_date')
            ->whereBetween('renewal_date', [now(), now()->addDays(30)])
            ->count();

        return [
            Stat::make('Active contracts', $active->count())
                ->description(__('Active licenses & contracts'))
                ->color('primary'),

            Stat::make('MRR', '€ '.number_format($mrr, 0))
                ->description('ARR: € '.number_format($arr, 0))
                ->color('success'),

            Stat::make('Annual margin', '€ '.number_format($annualMargin, 0))
                ->description(__('Annualized margin'))
                ->color('success'),

            Stat::make('Renews in < 30 days', $upcoming)
                ->description(__('Needs attention'))
                ->color($upcoming > 0 ? 'warning' : 'gray'),
        ];
    }
}
