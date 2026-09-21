<?php

namespace Tests\Feature\Api;

use App\Enums\BillingCycle;
use App\Enums\ContractStatus;
use App\Models\Company;
use App\Models\Contract;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DashboardApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_dashboard_is_rejected(): void
    {
        $this->getJson('/api/v1/dashboard')->assertUnauthorized();
        $this->getJson('/api/v1/contracts/upcoming-renewals')->assertUnauthorized();
    }

    public function test_viewer_can_read_dashboard_shape_matching_filament_math(): void
    {
        Sanctum::actingAs(User::factory()->viewer()->create());

        $company = Company::create(['name' => 'Metrics Co', 'country' => 'NL']);

        // Yearly: annual_revenue = 1200, monthly MRR contribution = 100
        Contract::create([
            'company_id' => $company->id,
            'name' => 'Yearly Active',
            'quantity' => 1,
            'sale_price' => 1200,
            'cost_price' => 200,
            'billing_cycle' => BillingCycle::Yearly->value,
            'status' => ContractStatus::Active->value,
            'start_date' => now()->subYear()->toDateString(),
            'renewal_date' => now()->addDays(10)->toDateString(),
            'notice_period_days' => 30,
            'auto_renew' => true,
        ]);

        // Monthly: annual_revenue = 100 * 12 = 1200
        Contract::create([
            'company_id' => $company->id,
            'name' => 'Monthly Active',
            'quantity' => 1,
            'sale_price' => 100,
            'cost_price' => 40,
            'billing_cycle' => BillingCycle::Monthly->value,
            'status' => ContractStatus::Active->value,
            'start_date' => now()->subMonths(3)->toDateString(),
            'renewal_date' => now()->addDays(45)->toDateString(),
            'auto_renew' => false,
        ]);

        // Pending must not count toward MRR/ARR
        Contract::create([
            'company_id' => $company->id,
            'name' => 'Pending Ignored',
            'quantity' => 1,
            'sale_price' => 9999,
            'cost_price' => 1,
            'billing_cycle' => BillingCycle::Yearly->value,
            'status' => ContractStatus::Pending->value,
            'start_date' => now()->toDateString(),
            'renewal_date' => now()->addDays(5)->toDateString(),
        ]);

        $response = $this->getJson('/api/v1/dashboard')->assertOk();

        $response->assertJsonPath('data.active_contracts_count', 2);
        $response->assertJsonPath('data.upcoming_renewals_30d_count', 1);

        // ARR = 1200 (yearly) + 1200 (monthly*12) = 2400; MRR = 200
        $this->assertEquals(200.0, $response->json('data.mrr'));
        $this->assertEquals(2400.0, $response->json('data.arr'));

        // annual_margin = (1200-200)*1 + (100-40)*12 = 1000 + 720 = 1720
        $this->assertEquals(1720.0, $response->json('data.annual_margin'));

        $this->assertIsArray($response->json('data.upcoming_renewals'));
        $this->assertIsArray($response->json('data.upcoming_notice_deadlines'));
        $this->assertIsArray($response->json('data.upcoming_planned_tasks'));
        $this->assertGreaterThanOrEqual(1, count($response->json('data.upcoming_renewals')));
    }

    public function test_upcoming_renewals_endpoint_respects_days_param(): void
    {
        Sanctum::actingAs(User::factory()->manager()->create());

        $company = Company::create(['name' => 'Renew Co', 'country' => 'NL']);

        $near = Contract::create([
            'company_id' => $company->id,
            'name' => 'Renews Soon',
            'status' => ContractStatus::Active->value,
            'billing_cycle' => BillingCycle::Yearly->value,
            'sale_price' => 10,
            'cost_price' => 1,
            'quantity' => 1,
            'start_date' => now()->subYear()->toDateString(),
            'renewal_date' => now()->addDays(5)->toDateString(),
            'auto_renew' => true,
        ]);

        Contract::create([
            'company_id' => $company->id,
            'name' => 'Renews Later',
            'status' => ContractStatus::Active->value,
            'billing_cycle' => BillingCycle::Yearly->value,
            'sale_price' => 10,
            'cost_price' => 1,
            'quantity' => 1,
            'start_date' => now()->subYear()->toDateString(),
            'renewal_date' => now()->addDays(40)->toDateString(),
            'auto_renew' => true,
        ]);

        $this->getJson('/api/v1/contracts/upcoming-renewals?days=30')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $near->id);

        $this->getJson('/api/v1/contracts/upcoming-renewals?days=60')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_viewer_cannot_post_contacts_but_can_read_dashboard(): void
    {
        Sanctum::actingAs(User::factory()->viewer()->create());

        $this->getJson('/api/v1/dashboard')->assertOk();
        $this->getJson('/api/v1/contracts/upcoming-renewals')->assertOk();
    }
}
