<?php

namespace Tests\Feature\Api;

use App\Models\Product;
use App\Models\ProductComponent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProductComponentApiTest extends TestCase
{
    use RefreshDatabase;

    protected function makeProduct(string $name, float $cost = 10): Product
    {
        return Product::create([
            'name' => $name,
            'default_cost_price' => $cost,
            'default_sale_price' => $cost * 2,
            'currency' => 'EUR',
            'billing_cycle' => 'yearly',
            'active' => true,
        ]);
    }

    public function test_unauthenticated_requests_are_rejected(): void
    {
        $this->getJson('/api/v1/product-components')->assertUnauthorized();
    }

    public function test_viewer_can_list_nested_components_but_cannot_write(): void
    {
        $viewer = User::factory()->viewer()->create();
        Sanctum::actingAs($viewer);

        $bundle = $this->makeProduct('MSP Bundle', 0);
        $part = $this->makeProduct('Antivirus', 5);
        $link = ProductComponent::create([
            'product_id' => $bundle->id,
            'component_id' => $part->id,
            'quantity' => 2,
        ]);

        $this->getJson("/api/v1/products/{$bundle->id}/components")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.quantity', 2)
            ->assertJsonPath('data.0.component_id', $part->id);

        $this->getJson("/api/v1/product-components/{$link->id}")
            ->assertOk()
            ->assertJsonPath('data.product_id', $bundle->id);

        $this->postJson("/api/v1/products/{$bundle->id}/components", [
            'component_id' => $part->id,
            'quantity' => 1,
        ])->assertForbidden();
    }

    public function test_sales_can_create_update_and_reject_self_component(): void
    {
        $sales = User::factory()->sales()->create();
        Sanctum::actingAs($sales);

        $bundle = $this->makeProduct('Pack', 0);
        $partA = $this->makeProduct('Part A', 10);
        $partB = $this->makeProduct('Part B', 20);

        $create = $this->postJson("/api/v1/products/{$bundle->id}/components", [
            'component_id' => $partA->id,
            'quantity' => 3,
        ]);

        $create->assertCreated()
            ->assertJsonPath('data.product_id', $bundle->id)
            ->assertJsonPath('data.component_id', $partA->id)
            ->assertJsonPath('data.quantity', 3);

        $id = $create->json('data.id');

        $this->putJson("/api/v1/product-components/{$id}", [
            'component_id' => $partB->id,
            'quantity' => 1,
        ])
            ->assertOk()
            ->assertJsonPath('data.component_id', $partB->id)
            ->assertJsonPath('data.quantity', 1);

        $this->postJson('/api/v1/product-components', [
            'product_id' => $bundle->id,
            'component_id' => $bundle->id,
            'quantity' => 1,
        ])->assertStatus(422);

        $this->deleteJson("/api/v1/product-components/{$id}")
            ->assertForbidden();
    }

    public function test_admin_can_delete_product_component(): void
    {
        $admin = User::factory()->admin()->create();
        Sanctum::actingAs($admin);

        $bundle = $this->makeProduct('Pack');
        $part = $this->makeProduct('Part');
        $link = ProductComponent::create([
            'product_id' => $bundle->id,
            'component_id' => $part->id,
            'quantity' => 1,
        ]);

        $this->deleteJson("/api/v1/product-components/{$link->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('product_components', ['id' => $link->id]);
    }

    public function test_duplicate_component_on_same_product_is_rejected(): void
    {
        Sanctum::actingAs(User::factory()->sales()->create());

        $bundle = $this->makeProduct('Pack');
        $part = $this->makeProduct('Part');
        ProductComponent::create([
            'product_id' => $bundle->id,
            'component_id' => $part->id,
            'quantity' => 1,
        ]);

        $this->postJson('/api/v1/product-components', [
            'product_id' => $bundle->id,
            'component_id' => $part->id,
            'quantity' => 2,
        ])->assertStatus(422);
    }
}
