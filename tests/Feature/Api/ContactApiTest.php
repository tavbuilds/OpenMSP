<?php

namespace Tests\Feature\Api;

use App\Models\Company;
use App\Models\Contact;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ContactApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_requests_are_rejected(): void
    {
        $this->getJson('/api/v1/contacts')->assertUnauthorized();
        $this->postJson('/api/v1/contacts', ['name' => 'Ada'])->assertUnauthorized();
    }

    public function test_viewer_can_list_and_show_but_cannot_write(): void
    {
        $viewer = User::factory()->viewer()->create();
        Sanctum::actingAs($viewer);

        $company = Company::create(['name' => 'Contact Co', 'country' => 'NL']);
        $contact = Contact::create([
            'company_id' => $company->id,
            'name' => 'Ada Lovelace',
            'email' => 'ada@example.com',
            'job_title' => 'CTO',
            'is_primary' => true,
        ]);

        $this->getJson('/api/v1/contacts')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Ada Lovelace');

        $this->getJson("/api/v1/contacts/{$contact->id}")
            ->assertOk()
            ->assertJsonPath('data.email', 'ada@example.com')
            ->assertJsonPath('data.is_primary', true);

        $this->getJson("/api/v1/companies/{$company->id}/contacts")
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->postJson('/api/v1/contacts', [
            'company_id' => $company->id,
            'name' => 'Nope',
        ])->assertForbidden();

        $this->putJson("/api/v1/contacts/{$contact->id}", ['name' => 'Nope'])
            ->assertForbidden();

        $this->deleteJson("/api/v1/contacts/{$contact->id}")
            ->assertForbidden();
    }

    public function test_sales_can_create_update_via_top_level_and_nested(): void
    {
        $sales = User::factory()->sales()->create();
        Sanctum::actingAs($sales);

        $company = Company::create(['name' => 'Nested Co', 'country' => 'NL']);

        $create = $this->postJson('/api/v1/contacts', [
            'company_id' => $company->id,
            'name' => 'Grace Hopper',
            'email' => 'grace@example.com',
            'phone' => '+31600000000',
            'job_title' => 'Engineer',
            'is_primary' => true,
        ]);

        $create->assertCreated()
            ->assertJsonPath('data.name', 'Grace Hopper')
            ->assertJsonPath('data.company_id', $company->id);

        $id = $create->json('data.id');

        $this->putJson("/api/v1/contacts/{$id}", [
            'job_title' => 'Rear Admiral',
            'is_primary' => false,
        ])
            ->assertOk()
            ->assertJsonPath('data.job_title', 'Rear Admiral')
            ->assertJsonPath('data.is_primary', false);

        $nested = $this->postJson("/api/v1/companies/{$company->id}/contacts", [
            'name' => 'Alan Turing',
            'email' => 'alan@example.com',
        ]);

        $nested->assertCreated()
            ->assertJsonPath('data.name', 'Alan Turing')
            ->assertJsonPath('data.company_id', $company->id);

        $this->getJson("/api/v1/contacts?company_id={$company->id}&search=turing")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Alan Turing');

        $this->deleteJson("/api/v1/contacts/{$id}")
            ->assertForbidden();
    }

    public function test_admin_can_delete_contact(): void
    {
        $admin = User::factory()->admin()->create();
        Sanctum::actingAs($admin);

        $company = Company::create(['name' => 'Delete Contact Co', 'country' => 'NL']);
        $contact = Contact::create([
            'company_id' => $company->id,
            'name' => 'Delete Me',
        ]);

        $this->deleteJson("/api/v1/contacts/{$contact->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('contacts', ['id' => $contact->id]);
    }

    public function test_index_supports_is_primary_filter_and_sort(): void
    {
        Sanctum::actingAs(User::factory()->manager()->create());

        $company = Company::create(['name' => 'Sort Co', 'country' => 'NL']);
        Contact::create([
            'company_id' => $company->id,
            'name' => 'Secondary',
            'is_primary' => false,
        ]);
        $primary = Contact::create([
            'company_id' => $company->id,
            'name' => 'Primary',
            'is_primary' => true,
        ]);

        $this->getJson('/api/v1/contacts?is_primary=true')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $primary->id);

        $sorted = $this->getJson('/api/v1/contacts?sort=name&order=asc')
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $this->assertSame('Primary', $sorted->json('data.0.name'));
        $this->assertSame('Secondary', $sorted->json('data.1.name'));
    }
}
