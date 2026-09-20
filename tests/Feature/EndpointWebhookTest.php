<?php

namespace Tests\Feature;

use App\Enums\EndpointKind;
use App\Enums\EndpointSource;
use App\Models\Endpoint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EndpointWebhookTest extends TestCase
{
    use RefreshDatabase;

    public function test_webhook_updates_expiry_and_resets_notification_cycle(): void
    {
        $endpoint = Endpoint::create([
            'name' => 'VPN cert',
            'kind' => EndpointKind::Certificate,
            'expires_at' => now()->addDays(5)->toDateString(),
            'sent_offsets' => [30, 14, 7],
            'notify_7' => true,
        ]);

        $this->postJson('/hooks/endpoints/'.$endpoint->webhook_token, [
            'expires_at' => now()->addDays(80)->toDateString(),
            'hostname' => 'vpn.example.com',
        ])->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('parsed', true);

        $endpoint->refresh();
        $this->assertSame(EndpointSource::Webhook, $endpoint->source);
        $this->assertSame('vpn.example.com', $endpoint->hostname);
        $this->assertSame(now()->addDays(80)->toDateString(), $endpoint->expires_at->toDateString());
        $this->assertSame([], $endpoint->sent_offsets ?? []);
        $this->assertSame('ok', $endpoint->last_status);
    }

    public function test_unknown_token_is_404(): void
    {
        $this->postJson('/hooks/endpoints/not-a-real-token', ['expires_at' => '2026-01-01'])
            ->assertNotFound();
    }

    public function test_unparsed_payload_still_succeeds(): void
    {
        $endpoint = Endpoint::create([
            'name' => 'Mail cert',
            'kind' => EndpointKind::Certificate,
        ]);

        $this->postJson('/hooks/endpoints/'.$endpoint->webhook_token, [
            'foo' => 'bar',
        ])->assertOk()->assertJsonPath('parsed', false);

        $this->assertNotNull($endpoint->fresh()->last_payload);
    }
}
