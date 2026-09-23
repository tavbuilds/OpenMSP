<?php

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;

/**
 * The dashboard's five watchlists, one at a time.
 *
 * Stacked, they ran to roughly 2,000px of scroll for a handful of rows, and
 * the one thing that was on fire looked exactly like the four that weren't.
 * The tables themselves are untouched — this only picks which is on screen
 * and puts the count of the others on their tab. A list with nothing in it
 * gets no tab; when all five are empty the deck collapses to one line.
 */
class AttentionBoard extends Widget
{
    protected string $view = 'filament.widgets.attention-board';

    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        $panels = collect([
            [
                'key' => 'collections',
                'label' => __('Collections'),
                'widget' => FailedCollections::class,
                'tone' => 'danger',
            ],
            [
                'key' => 'certificates',
                'label' => __('Certificates'),
                'widget' => UpcomingEndpointExpiries::class,
                'tone' => 'warning',
            ],
            [
                'key' => 'domains',
                'label' => __('Domains'),
                'widget' => UpcomingDomainExpiries::class,
                'tone' => 'warning',
            ],
            [
                'key' => 'notice',
                'label' => __('Notice deadlines'),
                'widget' => UpcomingNoticeDeadlines::class,
                'tone' => 'warning',
            ],
            [
                'key' => 'renewals',
                'label' => __('Renewals'),
                'widget' => UpcomingRenewals::class,
                'tone' => 'primary',
            ],
            [
                'key' => 'planning',
                'label' => __('Planning'),
                'widget' => UpcomingPlanning::class,
                'tone' => 'primary',
            ],
        ])
            ->map(fn (array $panel) => [...$panel, 'count' => $panel['widget']::attentionCount()])
            ->filter(fn (array $panel) => $panel['count'] > 0)
            ->values()
            ->all();

        return [
            'panels' => $panels,
            // Open on the most urgent list that has something in it; the order
            // above is the order of urgency.
            'activeKey' => $panels[0]['key'] ?? null,
        ];
    }
}
