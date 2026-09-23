<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\AttentionBoard;
use App\Filament\Widgets\PortfolioStats;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Widgets\Widget;

/**
 * The home page is two things: the numbers, and what is about to expire.
 *
 * Widget discovery registers every watchlist with the panel, which would
 * otherwise drop all five onto this page one under the other. They are
 * listed here through the deck instead.
 */
class Dashboard extends BaseDashboard
{
    /** @return array<class-string<Widget>> */
    public function getWidgets(): array
    {
        return [
            PortfolioStats::class,
            AttentionBoard::class,
        ];
    }

    public function getColumns(): int|array
    {
        return 1;
    }
}
