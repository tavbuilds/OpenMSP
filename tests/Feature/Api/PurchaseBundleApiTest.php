<?php

namespace Tests\Feature\Api;

use App\Models\PurchaseBundle;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PurchaseBundleApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_requests_are_rejected(): void
    {
        $this->getJson('/api/v1/purchase-bundles')->assertUnauthorized();
        $this->postJson('/api/v1/purchase-bundles', ['name' => 'Host'])->assertUnauthorized();
    }

    public function test_viewer_can_list_and_show_but_cannot_write(): void
    {
        $viewer = User::factory()->viewer()->create();
        Sanctum::actingAs($viewer);

        $bundle = PurchaseBundle::create([
            'name' => 'Hosting pool',
            'total_cost' => 1200,
            'currency' => 'EUR',
            'billing_cycle' => 'yearly',
            'allocation_method' => 'even',
        ]);

        $this->getJson('/api/v1/purchase-bundles')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Hosting pool');

        $this->getJson("/api/v1/purchase-bundles/{$bundle->id}")
            ->assertOk()
            ->assertJsonPath('data.name', 'Hosting pool')
            ->assertJsonStructure([
                'data' => [
                    'annual_cost',
                    'active_contracts_count',
                    'allocated_annual_cost_per_contract',
                    'recovery_percentage',
                ],
            ]);

        $this->postJson('/api/v1/purchase-bundles', ['name' => 'Nope'])
            ->assertForbidden();

        $this->putJson("/api/v1/purchase-bundles/{$bundle->id}", ['name' => 'Nope'])
            ->assertForbidden();

        $this->deleteJson("/api/v1/purchase-bundles/{$bundle->id}")
            ->assertForbidden();
    }

    public function test_sales_can_create_and_update_purchase_bundle(): void
    {
        $sales = User::factory()->sales()->create();
        Sanctum::actingAs($sales);

        $vendor = Vendor::create(['name' => 'CloudCo']);

        $create = $this->postJson('/api/v1/purchase-bundles', [
            'vendor_id' => $vendor->id,
            'name' => 'Reseller pack',
            'reference' => 'PO-100',
            'total_cost' => 500,
            'currency' => 'EUR',
            'billing_cycle' => 'yearly',
            'allocation_method' => 'even',
        ]);

        $create->assertCreated()
            ->assertJsonPath('data.name', 'Reseller pack')
            ->assertJsonPath('data.vendor_id', $vendor->id);

        $id = $create->json('data.id');

        $this->putJson("/api/v1/purchase-bundles/{$id}", [
            'name' => 'Reseller pack updated',
            'total_cost' => 750,
        ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Reseller pack updated')
            ->assertJsonPath('data.total_cost', '750.00');

        $this->assertDatabaseHas('purchase_bundles', [
            'id' => $id,
            'name' => 'Reseller pack updated',
        ]);

        $this->deleteJson("/api/v1/purchase-bundles/{$id}")
            ->assertForbidden();
    }

    public function test_admin_can_delete_purchase_bundle(): void
    {
        $admin = User::factory()->admin()->create();
        Sanctum::actingAs($admin);

        $bundle = PurchaseBundle::create([
            'name' => 'Delete me',
            'total_cost' => 10,
            'billing_cycle' => 'monthly',
        ]);

        $this->deleteJson("/api/v1/purchase-bundles/{$bundle->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('purchase_bundles', ['id' => $bundle->id]);
    }

    public function test_index_supports_search_vendor_and_cost_filters(): void
    {
        Sanctum::actingAs(User::factory()->manager()->create());

        $vendor = Vendor::create(['name' => 'Alpha Vendor']);

        PurchaseBundle::create([
            'vendor_id' => $vendor->id,
            'name' => 'Alpha Hosting',
            'total_cost' => 100,
            'billing_cycle' => 'yearly',
        ]);
        PurchaseBundle::create([
            'name' => 'Beta Storage',
            'total_cost' => 900,
            'billing_cycle' => 'yearly',
        ]);

        $this->getJson('/api/v1/purchase-bundles?search=alpha')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Alpha Hosting');

        $this->getJson("/api/v1/purchase-bundles?vendor_id={$vendor->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->getJson('/api/v1/purchase-bundles?cost_min=500')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Beta Storage');
    }
}
