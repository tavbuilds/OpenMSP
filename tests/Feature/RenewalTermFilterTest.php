<?php

namespace Tests\Feature;

use App\Enums\BillingCycle;
use App\Enums\ContractStatus;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Contract;
use App\Models\User;
use App\Notifications\ContractRenewalReminder;
use App\Notifications\CustomerContractReminder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Renewal watching is for committed terms only. A monthly contract can be
 * cancelled at any month boundary, so its renewal is not a deadline; one-time
 * work never renews. Both used to fill the dashboard and trigger reminder mail
 * every cycle.
 */
class RenewalTermFilterTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();
        $this->company = Company::create(['name' => 'Term Co', 'country' => 'NL']);
    }

    private function contract(BillingCycle $cycle, string $name, int $inDays = 10, array $overrides = []): Contract
    {
        return Contract::create(array_merge([
            'company_id' => $this->company->id,
            'name' => $name,
            'type' => 'license',
            'quantity' => 1,
            'cost_price' => 10,
            'sale_price' => 100,
            'currency' => 'EUR',
            'billing_cycle' => $cycle->value,
            'start_date' => now()->subYear()->toDateString(),
            'renewal_date' => now()->addDays($inDays)->toDateString(),
            'notice_period_days' => 0,
            'status' => ContractStatus::Active->value,
        ], $overrides));
    }

    public function test_only_quarterly_and_yearly_have_a_renewal_term(): void
    {
        $this->assertTrue(BillingCycle::Quarterly->hasRenewalTerm());
        $this->assertTrue(BillingCycle::Yearly->hasRenewalTerm());
        $this->assertFalse(BillingCycle::Monthly->hasRenewalTerm());
        $this->assertFalse(BillingCycle::Once->hasRenewalTerm());

        $this->assertSame(['quarterly', 'yearly'], BillingCycle::renewalTermValues());
    }

    public function test_scope_keeps_committed_terms_and_drops_the_rest(): void
    {
        $this->contract(BillingCycle::Yearly, 'Yearly');
        $this->contract(BillingCycle::Quarterly, 'Quarterly');
        $this->contract(BillingCycle::Monthly, 'Monthly');
        $this->contract(BillingCycle::Once, 'One-time');
        $this->contract(BillingCycle::Yearly, 'Cancelled', 10, ['status' => ContractStatus::Cancelled->value]);
        $this->contract(BillingCycle::Yearly, 'No date', 10, ['renewal_date' => null]);

        $names = Contract::query()->withUpcomingRenewalTerm()->pluck('name')->sort()->values()->all();

        $this->assertSame(['Quarterly', 'Yearly'], $names);
    }

    public function test_dashboard_api_ignores_monthly_and_one_time_renewals(): void
    {
        Sanctum::actingAs(User::factory()->manager()->create());

        $this->contract(BillingCycle::Yearly, 'Yearly', 10, ['notice_period_days' => 5]);
        $this->contract(BillingCycle::Monthly, 'Monthly', 12, ['notice_period_days' => 5]);
        $this->contract(BillingCycle::Once, 'One-time', 14, ['notice_period_days' => 5]);

        $data = $this->getJson('/api/v1/dashboard')->assertOk()->json('data');

        $this->assertSame(1, $data['upcoming_renewals_30d_count']);
        $this->assertSame(['Yearly'], array_column($data['upcoming_renewals'], 'name'));
        $this->assertSame(['Yearly'], array_column($data['upcoming_notice_deadlines'], 'name'));
    }

    public function test_monthly_contracts_still_count_toward_mrr_and_arr(): void
    {
        Sanctum::actingAs(User::factory()->manager()->create());

        // 100/month = 1200 a year. Revenue is not affected by renewal watching.
        $this->contract(BillingCycle::Monthly, 'Monthly', 12);

        $data = $this->getJson('/api/v1/dashboard')->assertOk()->json('data');

        $this->assertEquals(1200.0, $data['arr']);
        $this->assertEquals(100.0, $data['mrr']);
        $this->assertSame(0, $data['upcoming_renewals_30d_count']);
    }

    public function test_upcoming_renewals_endpoint_ignores_monthly(): void
    {
        Sanctum::actingAs(User::factory()->manager()->create());

        $this->contract(BillingCycle::Yearly, 'Yearly');
        $this->contract(BillingCycle::Monthly, 'Monthly');

        $names = array_column(
            $this->getJson('/api/v1/contracts/upcoming-renewals')->assertOk()->json('data'),
            'name',
        );

        $this->assertSame(['Yearly'], $names);
    }

    public function test_reminders_skip_monthly_contracts(): void
    {
        Notification::fake();
        User::factory()->admin()->create();

        $contact = Contact::create([
            'company_id' => $this->company->id,
            'name' => 'Klant',
            'email' => 'klant@example.com',
        ]);

        $this->contract(BillingCycle::Monthly, 'Monthly', 7, ['notify_renewals' => true]);

        $this->artisan('contracts:send-renewal-reminders')->assertSuccessful();

        Notification::assertNothingSent();

        $this->contract(BillingCycle::Yearly, 'Yearly', 7, ['notify_renewals' => true]);

        $this->artisan('contracts:send-renewal-reminders')->assertSuccessful();

        Notification::assertSentTo($contact, CustomerContractReminder::class);
        Notification::assertSentTimes(ContractRenewalReminder::class, 1);
    }
}
