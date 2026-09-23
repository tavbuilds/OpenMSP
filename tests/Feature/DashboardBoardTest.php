<?php

namespace Tests\Feature;

use App\Enums\BillingCycle;
use App\Enums\ContractStatus;
use App\Filament\Pages\Dashboard;
use App\Filament\Widgets\AttentionBoard;
use App\Filament\Widgets\FailedCollections;
use App\Filament\Widgets\PortfolioStats;
use App\Filament\Widgets\UpcomingEndpointExpiries;
use App\Filament\Widgets\UpcomingNoticeDeadlines;
use App\Filament\Widgets\UpcomingPlanning;
use App\Filament\Widgets\UpcomingRenewals;
use App\Models\Company;
use App\Models\Contract;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The dashboard used to stack five watchlists, so the one thing on fire
 * looked exactly like the four that were not. They live in a tabbed deck
 * now; these pin the parts of that which are easy to break silently.
 */
class DashboardBoardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->admin()->create());
    }

    private function renewingContract(string $name, BillingCycle $cycle = BillingCycle::Yearly): Contract
    {
        $company = Company::create(['name' => 'Board Co', 'country' => 'NL']);

        return Contract::create([
            'company_id' => $company->id,
            'name' => $name,
            'type' => 'license',
            'quantity' => 1,
            'cost_price' => 10,
            'sale_price' => 100,
            'currency' => 'EUR',
            'billing_cycle' => $cycle->value,
            'start_date' => now()->subYear()->toDateString(),
            'renewal_date' => now()->addDays(10)->toDateString(),
            'notice_period_days' => 0,
            'status' => ContractStatus::Active->value,
        ]);
    }

    /**
     * Widget discovery registers every watchlist with the panel. If the page
     * ever falls back to that list, all five land on the home page again.
     */
    public function test_the_dashboard_lists_only_the_stats_and_the_deck(): void
    {
        $this->assertSame(
            [PortfolioStats::class, AttentionBoard::class],
            (new Dashboard)->getWidgets(),
        );
    }

    public function test_a_watchlist_with_rows_gets_a_tab(): void
    {
        $this->renewingContract('Yearly service');

        Livewire::test(AttentionBoard::class)
            ->assertSee(__('Renewals'))
            ->assertDontSee(__('Nothing needs attention in the next 60 days.'));
    }

    public function test_a_watchlist_with_nothing_in_it_gets_no_tab(): void
    {
        // Monthly renewals are not deadlines, so nothing is watching this one.
        $this->renewingContract('Monthly service', BillingCycle::Monthly);

        Livewire::test(AttentionBoard::class)
            ->assertSee(__('Nothing needs attention in the next 60 days.'))
            ->assertDontSee(__('Renewals'));
    }

    /**
     * A table widget's heading comes from getTableHeading(); a getHeading()
     * override is never called, so these silently stayed English.
     */
    public function test_watchlist_headings_are_translated(): void
    {
        $this->app->setLocale('nl');

        $headings = [
            FailedCollections::class => 'Failed collections',
            UpcomingEndpointExpiries::class => 'Endpoints / certificates (60 days or expired)',
            UpcomingNoticeDeadlines::class => 'Notice deadlines (60 days)',
            UpcomingPlanning::class => 'Upcoming planning (60 days)',
            UpcomingRenewals::class => 'Upcoming renewals',
        ];

        foreach ($headings as $widget => $english) {
            $dutch = __($english);
            $this->assertNotSame($english, $dutch, "lang/nl.json has no entry for {$english}");

            $this->assertSame(
                $dutch,
                Livewire::test($widget)->instance()->getTable()->getHeading(),
            );
        }
    }

    /**
     * The count on the tab and the rows behind it come from one query, so a
     * tab can never promise rows the table does not have.
     */
    public function test_the_tab_count_comes_from_the_table_query(): void
    {
        $this->renewingContract('Yearly service');
        $this->renewingContract('Monthly service', BillingCycle::Monthly);

        $this->assertSame(1, UpcomingRenewals::attentionCount());
        $this->assertSame(
            UpcomingRenewals::attentionQuery()->count(),
            UpcomingRenewals::attentionCount(),
        );
    }
}
