<?php

namespace Tests\Feature\Api;

use App\Enums\DomainSource;
use App\Models\Company;
use App\Models\Domain;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DomainApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_requests_are_rejected(): void
    {
        $this->getJson('/api/v1/domains')->assertUnauthorized();
        $this->postJson('/api/v1/domains', ['name' => 'example.nl'])->assertUnauthorized();
    }

    public function test_viewer_can_list_and_cannot_write(): void
    {
        Sanctum::actingAs(User::factory()->viewer()->create());

        Domain::create([
            'name' => 'Example.NL',
            'expires_at' => now()->addDays(10)->toDateString(),
        ]);
        Domain::create([
            'name' => 'gone.nl',
            'expires_at' => now()->subDay()->toDateString(),
            'removed_at' => now(),
        ]);

        $this->getJson('/api/v1/domains')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'example.nl')
            ->assertJsonPath('data.0.extension', 'nl');

        $this->getJson('/api/v1/domains?lifecycle=removed')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'gone.nl');

        $this->getJson('/api/v1/domains?lifecycle=sideways')->assertUnprocessable();

        $this->postJson('/api/v1/domains', ['name' => 'nope.nl'])->assertForbidden();
    }

    public function test_sales_can_create_assign_and_cannot_change_registrar_fields(): void
    {
        Sanctum::actingAs(User::factory()->sales()->create());
        $company = Company::create(['name' => 'Acme BV', 'country' => 'NL']);

        $create = $this->postJson('/api/v1/domains', [
            'name' => 'Hand.Example',
            'company_id' => $company->id,
            'renewal_date' => now()->addDays(12)->toDateString(),
            'auto_renew' => false,
            'notes' => 'Typed in',
        ])->assertCreated();

        $create->assertJsonPath('data.name', 'hand.example')
            ->assertJsonPath('data.source', 'manual')
            ->assertJsonPath('data.company_name', 'Acme BV')
            ->assertJsonPath('data.notes_visible_to_customer', false);

        $id = $create->json('data.id');
        $domain = Domain::query()->findOrFail($id);
        $domain->forceFill(['sent_offsets' => [30]])->save();

        $this->patchJson("/api/v1/domains/{$id}", [
            'renewal_date' => now()->addDays(40)->toDateString(),
        ])->assertOk();

        $this->assertSame([], $domain->fresh()->sent_offsets);

        $synced = Domain::create([
            'name' => 'sync.nl',
            'source' => DomainSource::OpenProvider,
            'source_id' => 'op-1',
            'expires_at' => '2027-06-01',
            'auto_renew' => true,
            'status' => 'ACT',
        ]);

        $this->patchJson("/api/v1/domains/{$synced->id}", [
            'expires_at' => '2028-01-01',
        ])->assertUnprocessable()->assertJsonValidationErrors(['expires_at']);

        $this->assertSame('2027-06-01', $synced->fresh()->expires_at->toDateString());

        $this->patchJson("/api/v1/domains/{$synced->id}", [
            'notes' => 'Ours',
            'company_id' => $company->id,
        ])->assertOk()
            ->assertJsonPath('data.notes', 'Ours')
            ->assertJsonPath('data.company_id', $company->id)
            ->assertJsonPath('data.expires_at', '2027-06-01');

        $loose = Domain::create(['name' => 'loose.nl']);
        $other = Domain::create(['name' => 'other.nl']);

        $this->postJson('/api/v1/domains/assign', [
            'company_id' => $company->id,
            'ids' => [$loose->id, $other->id],
        ])->assertOk()
            ->assertJsonPath('data.assigned', 2);

        $this->assertSame($company->id, $loose->fresh()->company_id);

        $this->deleteJson("/api/v1/domains/{$id}")->assertForbidden();
    }

    public function test_upcoming_skips_removed_and_far_dates(): void
    {
        Sanctum::actingAs(User::factory()->viewer()->create());

        Domain::create(['name' => 'soon.nl', 'renewal_date' => now()->addDays(5)->toDateString()]);
        Domain::create(['name' => 'late.nl', 'renewal_date' => now()->addDays(90)->toDateString()]);
        Domain::create(['name' => 'old.nl', 'renewal_date' => now()->subDay()->toDateString()]);
        Domain::create([
            'name' => 'gone.nl',
            'renewal_date' => now()->subDay()->toDateString(),
            'removed_at' => now(),
        ]);

        $this->getJson('/api/v1/domains/upcoming-expiries?days=30')
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $names = collect($this->getJson('/api/v1/domains/upcoming-expiries?days=30')->json('data'))->pluck('name');
        $this->assertEqualsCanonicalizing(['soon.nl', 'old.nl'], $names->all());
    }

    public function test_manager_can_delete(): void
    {
        Sanctum::actingAs(User::factory()->manager()->create());
        $domain = Domain::create(['name' => 'drop.nl']);

        $this->deleteJson("/api/v1/domains/{$domain->id}")->assertNoContent();
        $this->assertDatabaseMissing('domains', ['id' => $domain->id]);
    }

    public function test_list_domains_tool_returns_the_portfolio(): void
    {
        Sanctum::actingAs(User::factory()->viewer()->create());
        Domain::create(['name' => 'via-mcp.nl']);

        $response = $this->postJson('/mcp', [
            'jsonrpc' => '2.0',
            'id' => 21,
            'method' => 'tools/call',
            'params' => ['name' => 'list_domains', 'arguments' => []],
        ])->assertOk();

        $this->assertFalse($response->json('result.isError'));
        $this->assertStringContainsString('via-mcp.nl', $response->json('result.content.0.text'));
    }
}
