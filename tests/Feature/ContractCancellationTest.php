<?php

namespace Tests\Feature;

use App\Enums\BillingCycle;
use App\Enums\ContractStatus;
use App\Models\Company;
use App\Models\Contract;
use App\Support\ContractCancellation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * When a cancellation takes effect, and what the customer is told it will
 * cost them. Getting these dates wrong means invoicing for a service we
 * stopped providing, or providing one we stopped invoicing.
 */
class ContractCancellationTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();
        $this->company = Company::create(['name' => 'Klant BV', 'country' => 'NL']);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function contract(array $overrides = []): Contract
    {
        return Contract::create(array_merge([
            'company_id' => $this->company->id,
            'name' => 'Hosting',
            'type' => 'license',
            'quantity' => 1,
            'cost_price' => 10,
            'sale_price' => 25,
            'currency' => 'EUR',
            'billing_cycle' => BillingCycle::Monthly,
            'start_date' => '2026-10-01',
            'next_invoice_date' => '2026-10-01',
            'notice_period_days' => 30,
            'status' => ContractStatus::Active,
        ], $overrides));
    }

    /**
     * The case as the operator put it: started 1 October, monthly, cancelled
     * on the 15th. The invoice of 1 November is the last one, and it pays
     * for November — so the service runs to the end of that month.
     */
    public function test_a_monthly_contract_cancelled_mid_month_is_billed_once_more(): void
    {
        Carbon::setTestNow('2026-10-15');

        $cancellation = ContractCancellation::for($this->contract());

        $this->assertSame('2026-11-01', $cancellation->lastInvoiceOn()?->toDateString());
        $this->assertSame('2026-11-30', $cancellation->endsOn()?->toDateString());
        $this->assertTrue($cancellation->customerMayCancel());
    }

    /** The anchor is rolled forward until it is in the future, not guessed. */
    public function test_a_stale_invoice_anchor_still_lands_on_the_next_real_date(): void
    {
        Carbon::setTestNow('2027-02-20');

        $cancellation = ContractCancellation::for($this->contract([
            'next_invoice_date' => '2026-10-01',
        ]));

        $this->assertSame('2027-03-01', $cancellation->lastInvoiceOn()?->toDateString());
        $this->assertSame('2027-03-31', $cancellation->endsOn()?->toDateString());
    }

    public function test_a_quarterly_contract_runs_to_the_end_of_the_quarter_it_pays_for(): void
    {
        Carbon::setTestNow('2026-10-15');

        $cancellation = ContractCancellation::for($this->contract([
            'billing_cycle' => BillingCycle::Quarterly,
            'next_invoice_date' => '2026-10-01',
        ]));

        $this->assertSame('2027-01-01', $cancellation->lastInvoiceOn()?->toDateString());
        $this->assertSame('2027-03-31', $cancellation->endsOn()?->toDateString());
    }

    /**
     * A year has been paid for already: cancelling stops the renewal, it
     * does not produce another invoice.
     */
    public function test_a_yearly_contract_cancelled_in_good_time_stops_at_its_renewal(): void
    {
        Carbon::setTestNow('2026-10-15');

        $cancellation = ContractCancellation::for($this->contract([
            'billing_cycle' => BillingCycle::Yearly,
            'renewal_date' => '2027-03-01',
        ]));

        $this->assertTrue($cancellation->customerMayCancel());
        $this->assertNull($cancellation->lastInvoiceOn());
        $this->assertSame('2027-03-01', $cancellation->endsOn()?->toDateString());
    }

    /** Thirty days out is the last day it can be done without asking. */
    public function test_the_notice_deadline_is_the_last_day_a_customer_can_cancel(): void
    {
        $contract = $this->contract([
            'billing_cycle' => BillingCycle::Yearly,
            'renewal_date' => '2026-12-01',
            'notice_period_days' => 30,
        ]);

        Carbon::setTestNow('2026-11-01');
        $this->assertTrue(ContractCancellation::for($contract)->customerMayCancel());

        Carbon::setTestNow('2026-11-02');
        $this->assertFalse(ContractCancellation::for($contract->fresh())->customerMayCancel());
    }

    public function test_inside_the_notice_window_the_customer_is_sent_to_support(): void
    {
        Carbon::setTestNow('2026-11-20');

        $cancellation = ContractCancellation::for($this->contract([
            'billing_cycle' => BillingCycle::Yearly,
            'renewal_date' => '2026-12-01',
        ]));

        $this->assertFalse($cancellation->customerMayCancel());
        $this->assertTrue($cancellation->isInsideNoticeWindow());
        $this->assertStringContainsString('support', $cancellation->summaryForCustomer());
    }

    /** A rolling term has no notice window to miss. */
    public function test_a_monthly_contract_is_never_inside_a_notice_window(): void
    {
        Carbon::setTestNow('2026-10-31');

        $cancellation = ContractCancellation::for($this->contract([
            'renewal_date' => '2026-11-01',
            'notice_period_days' => 30,
        ]));

        $this->assertFalse($cancellation->isInsideNoticeWindow());
        $this->assertTrue($cancellation->customerMayCancel());
    }

    public function test_a_one_off_has_nothing_to_cancel(): void
    {
        $cancellation = ContractCancellation::for($this->contract([
            'billing_cycle' => BillingCycle::Once,
        ]));

        $this->assertFalse($cancellation->isCancellable());
        $this->assertFalse($cancellation->customerMayCancel());
    }

    public function test_an_already_cancelled_contract_cannot_be_cancelled_again(): void
    {
        $cancellation = ContractCancellation::for($this->contract([
            'status' => ContractStatus::Cancelled,
        ]));

        $this->assertFalse($cancellation->isCancellable());
    }

    /** The customer sees the dates, not a shrug. */
    public function test_the_summary_names_both_dates(): void
    {
        Carbon::setTestNow('2026-10-15');

        $summary = ContractCancellation::for($this->contract())->summaryForCustomer();

        $this->assertStringContainsString('1 November 2026', $summary);
        $this->assertStringContainsString('30 November 2026', $summary);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }
}
