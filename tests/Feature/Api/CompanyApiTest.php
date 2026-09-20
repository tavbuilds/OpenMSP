<?php

namespace Tests\Feature\Api;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CompanyApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_requests_are_rejected(): void
    {
        $this->getJson('/api/v1/companies')->assertUnauthorized();
        $this->postJson('/api/v1/companies', ['name' => 'Acme'])->assertUnauthorized();
    }

    public function test_viewer_can_list_and_show_but_cannot_write(): void
    {
        $viewer = User::factory()->viewer()->create();
        Sanctum::actingAs($viewer);

        $company = Company::create([
            'name' => 'Viewer Co',
            'city' => 'Amsterdam',
            'country' => 'NL',
        ]);

        $this->getJson('/api/v1/companies')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Viewer Co');

        $this->getJson("/api/v1/companies/{$company->id}")
            ->assertOk()
            ->assertJsonPath('data.name', 'Viewer Co');

        $this->postJson('/api/v1/companies', ['name' => 'Nope'])
            ->assertForbidden();

        $this->putJson("/api/v1/companies/{$company->id}", ['name' => 'Nope'])
            ->assertForbidden();

        $this->deleteJson("/api/v1/companies/{$company->id}")
            ->assertForbidden();
    }

    public function test_sales_can_create_and_update_company(): void
    {
        $sales = User::factory()->sales()->create();
        Sanctum::actingAs($sales);

        $create = $this->postJson('/api/v1/companies', [
            'name' => 'Sales Customer',
            'email' => 'sales@example.com',
            'city' => 'Rotterdam',
            'country' => 'NL',
        ]);

        $create->assertCreated()
            ->assertJsonPath('data.name', 'Sales Customer');

        $id = $create->json('data.id');

        $this->putJson("/api/v1/companies/{$id}", [
            'name' => 'Sales Customer Updated',
            'city' => 'Utrecht',
        ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Sales Customer Updated')
            ->assertJsonPath('data.city', 'Utrecht');

        $this->assertDatabaseHas('companies', [
            'id' => $id,
            'name' => 'Sales Customer Updated',
            'city' => 'Utrecht',
        ]);

        $this->deleteJson("/api/v1/companies/{$id}")
            ->assertForbidden();
    }

    public function test_admin_can_delete_company(): void
    {
        $admin = User::factory()->admin()->create();
        Sanctum::actingAs($admin);

        $company = Company::create([
            'name' => 'Delete Me',
            'country' => 'NL',
        ]);

        $this->deleteJson("/api/v1/companies/{$company->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('companies', ['id' => $company->id]);
    }

    public function test_index_supports_search_and_pagination(): void
    {
        Sanctum::actingAs(User::factory()->manager()->create());

        Company::create(['name' => 'Alpha BV', 'country' => 'NL']);
        Company::create(['name' => 'Beta NV', 'country' => 'NL']);
        Company::create(['name' => 'Gamma BV', 'country' => 'NL']);

        $this->getJson('/api/v1/companies?search=alpha')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Alpha BV');

        $this->getJson('/api/v1/companies?per_page=2')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.per_page', 2);
    }
}
