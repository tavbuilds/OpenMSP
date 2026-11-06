<?php

namespace Tests\Feature\Api;

use App\Enums\EndpointKind;
use App\Enums\EndpointSource;
use App\Models\Endpoint;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EndpointApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_requests_are_rejected(): void
    {
        $this->getJson('/api/v1/endpoints')->assertUnauthorized();
        $this->postJson('/api/v1/endpoints', ['name' => 'VPN'])->assertUnauthorized();
    }

    public function test_sales_can_create_and_the_list_hides_the_webhook(): void
    {
        Sanctum::actingAs(User::factory()->sales()->create());

        $create = $this->postJson('/api/v1/endpoints', [
            'name' => 'VPN certificate',
            'kind' => 'certificate',
            'hostname' => 'vpn.example.nl',
            'url' => 'https://vpn.example.nl',
            'expires_at' => now()->addDays(10)->toDateString(),
        ])->assertCreated();

        $url = $create->json('data.webhook_url');
        $id = $create->json('data.id');

        $this->assertIsString($url);
        $this->assertStringContainsString('/hooks/endpoints/', $url);
        $create->assertJsonPath('data.kind', 'certificate')
            ->assertJsonPath('data.status', 'warning');

        $list = $this->getJson('/api/v1/endpoints')->assertOk();
        $this->assertStringNotContainsString($url, $list->getContent());
        $this->assertArrayNotHasKey('webhook_url', $list->json('data.0'));

        $this->getJson("/api/v1/endpoints/{$id}")
            ->assertOk()
            ->assertJsonPath('data.webhook_url', $url);

        $this->getJson('/api/v1/endpoints/upcoming?days=30')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $id);

        $this->deleteJson("/api/v1/endpoints/{$id}")->assertForbidden();
    }

    public function test_rotating_the_webhook_invalidates_the_old_url(): void
    {
        Sanctum::actingAs(User::factory()->manager()->create());

        $endpoint = Endpoint::create([
            'name' => 'Mail',
            'kind' => EndpointKind::Certificate,
            'expires_at' => now()->addYear()->toDateString(),
        ]);
        $old = $endpoint->webhookUrl();

        $rotated = $this->postJson("/api/v1/endpoints/{$endpoint->id}/rotate-webhook")
            ->assertOk();

        $new = $rotated->json('data.webhook_url');
        $this->assertNotSame($old, $new);

        $token = Str::after($old, '/hooks/endpoints/');
        $this->postJson('/hooks/endpoints/'.$token, ['expires_at' => '2027-01-01'])->assertNotFound();

        $this->deleteJson("/api/v1/endpoints/{$endpoint->id}")->assertNoContent();
    }

    public function test_a_new_expiry_restarts_the_reminder_cycle(): void
    {
        Sanctum::actingAs(User::factory()->sales()->create());

        $endpoint = Endpoint::create([
            'name' => 'Portal',
            'source' => EndpointSource::Webhook,
            'expires_at' => now()->addDays(3)->toDateString(),
        ]);
        $endpoint->forceFill(['sent_offsets' => [30, 14, 7]])->save();

        $this->patchJson("/api/v1/endpoints/{$endpoint->id}", [
            'expires_at' => now()->addYear()->toDateString(),
        ])->assertOk()
            ->assertJsonPath('data.source', 'webhook');

        $fresh = $endpoint->fresh();
        $this->assertSame([], $fresh->sent_offsets);
        $this->assertSame(EndpointSource::Webhook, $fresh->source);
    }
}
