<?php

namespace Tests\Feature\Api;

use App\Enums\BillingCycle;
use App\Enums\ContractStatus;
use App\Enums\ProductType;
use App\Models\Company;
use App\Models\Contract;
use App\Models\Product;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ContractApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_filters_by_status_billing_cycle_and_auto_renew(): void
    {
        Sanctum::actingAs(User::factory()->manager()->create());

        $company = Company::create(['name' => 'Filter Co', 'country' => 'NL']);

        $match = Contract::create([
            'company_id' => $company->id,
            'name' => 'Yearly Active Auto',
            'type' => ProductType::License->value,
            'billing_cycle' => BillingCycle::Yearly->value,
            'status' => ContractStatus::Active->value,
            'auto_renew' => true,
            'sale_price' => 200,
            'cost_price' => 50,
            'quantity' => 1,
            'start_date' => '2026-01-01',
            'renewal_date' => '2027-01-01',
        ]);

        Contract::create([
            'company_id' => $company->id,
            'name' => 'Monthly Pending Manual',
            'type' => ProductType::Support->value,
            'billing_cycle' => BillingCycle::Monthly->value,
            'status' => ContractStatus::Pending->value,
            'auto_renew' => false,
            'sale_price' => 80,
            'cost_price' => 20,
            'quantity' => 1,
            'start_date' => '2026-02-01',
            'renewal_date' => '2026-03-01',
        ]);

        $this->getJson('/api/v1/contracts?status=active&billing_cycle=yearly&auto_renew=true')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $match->id)
            ->assertJsonPath('data.0.name', 'Yearly Active Auto');
    }

    public function test_index_filters_sale_range_company_and_renewal_window(): void
    {
        Sanctum::actingAs(User::factory()->manager()->create());

        $alpha = Company::create(['name' => 'Alpha BV', 'country' => 'NL']);
        $beta = Company::create(['name' => 'Beta NV', 'country' => 'NL']);

        $target = Contract::create([
            'company_id' => $alpha->id,
            'name' => 'In Range',
            'sale_price' => 150,
            'cost_price' => 40,
            'quantity' => 2,
            'status' => ContractStatus::Active->value,
            'billing_cycle' => BillingCycle::Yearly->value,
            'start_date' => '2025-06-01',
            'renewal_date' => '2026-06-15',
            'auto_renew' => true,
        ]);

        Contract::create([
            'company_id' => $alpha->id,
            'name' => 'Too Cheap',
            'sale_price' => 50,
            'cost_price' => 10,
            'quantity' => 1,
            'status' => ContractStatus::Active->value,
            'billing_cycle' => BillingCycle::Yearly->value,
            'start_date' => '2025-06-01',
            'renewal_date' => '2026-06-15',
            'auto_renew' => true,
        ]);

        Contract::create([
            'company_id' => $beta->id,
            'name' => 'Wrong Company',
            'sale_price' => 150,
            'cost_price' => 40,
            'quantity' => 1,
            'status' => ContractStatus::Active->value,
            'billing_cycle' => BillingCycle::Yearly->value,
            'start_date' => '2025-06-01',
            'renewal_date' => '2026-06-15',
            'auto_renew' => true,
        ]);

        Contract::create([
            'company_id' => $alpha->id,
            'name' => 'Renews Later',
            'sale_price' => 150,
            'cost_price' => 40,
            'quantity' => 1,
            'status' => ContractStatus::Active->value,
            'billing_cycle' => BillingCycle::Yearly->value,
            'start_date' => '2025-06-01',
            'renewal_date' => '2027-01-01',
            'auto_renew' => true,
        ]);

        $this->getJson("/api/v1/contracts?company_id={$alpha->id}&sale_min=100&sale_max=200&renews_after=2026-01-01&renews_before=2026-12-31")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $target->id);
    }

    public function test_index_supports_margin_filter_search_and_sort(): void
    {
        Sanctum::actingAs(User::factory()->manager()->create());

        $vendor = Vendor::create(['name' => 'Contoso Soft']);
        $company = Company::create(['name' => 'Searchable Client', 'country' => 'NL']);
        $product = Product::create([
            'vendor_id' => $vendor->id,
            'name' => 'Contoso Suite',
            'sku' => 'CTS-1',
            'active' => true,
        ]);

        $highMargin = Contract::create([
            'company_id' => $company->id,
            'product_id' => $product->id,
            'vendor_id' => $vendor->id,
            'name' => 'High Margin Deal',
            'notes' => 'priority renewal',
            'sale_price' => 100,
            'cost_price' => 20,
            'quantity' => 2, // margin = 160
            'status' => ContractStatus::Active->value,
            'billing_cycle' => BillingCycle::Yearly->value,
            'start_date' => '2026-01-01',
            'renewal_date' => '2026-12-01',
            'auto_renew' => true,
        ]);

        $lowMargin = Contract::create([
            'company_id' => $company->id,
            'product_id' => $product->id,
            'vendor_id' => $vendor->id,
            'name' => 'Low Margin Deal',
            'sale_price' => 100,
            'cost_price' => 90,
            'quantity' => 1, // margin = 10
            'status' => ContractStatus::Active->value,
            'billing_cycle' => BillingCycle::Yearly->value,
            'start_date' => '2026-01-01',
            'renewal_date' => '2026-11-01',
            'auto_renew' => true,
        ]);

        $this->getJson('/api/v1/contracts?margin_min=100')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $highMargin->id);

        $this->getJson('/api/v1/contracts?search=searchable')
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $this->getJson('/api/v1/contracts?search=contoso+suite')
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $sorted = $this->getJson('/api/v1/contracts?sort=renewal_date&order=asc')
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $this->assertSame($lowMargin->id, $sorted->json('data.0.id'));
        $this->assertSame($highMargin->id, $sorted->json('data.1.id'));
    }

    public function test_viewer_can_list_but_cannot_write_contracts(): void
    {
        $viewer = User::factory()->viewer()->create();
        Sanctum::actingAs($viewer);

        $company = Company::create(['name' => 'Viewer Contracts', 'country' => 'NL']);
        $contract = Contract::create([
            'company_id' => $company->id,
            'name' => 'Read Only',
            'start_date' => '2026-01-01',
            'status' => ContractStatus::Active->value,
        ]);

        $this->getJson('/api/v1/contracts')->assertOk();
        $this->getJson("/api/v1/contracts/{$contract->id}")->assertOk();
        $this->postJson('/api/v1/contracts', [
            'company_id' => $company->id,
            'name' => 'Nope',
            'start_date' => '2026-01-01',
        ])->assertForbidden();
    }
}
