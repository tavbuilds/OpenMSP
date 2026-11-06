<?php

namespace Tests\Feature\Api;

use App\Enums\EndpointSource;
use App\Models\Endpoint;
use App\Models\Setting;
use App\Models\User;
use App\Support\PlatformSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UptimeKumaApiTest extends TestCase
{
    use RefreshDatabase;

    private const KEY = 'uk2_supersecretvalue';

    private const METRICS = <<<'PROM'
# HELP monitor_cert_days_remaining days
monitor_cert_days_remaining{monitor_name="Mail",monitor_type="http",monitor_url="https://mail.example.com",monitor_hostname="",monitor_port=""} 14
monitor_cert_days_remaining{monitor_name="VPN",monitor_type="http",monitor_url="https://vpn.example.com/",monitor_hostname="vpn.example.com",monitor_port="443"} 3
monitor_cert_days_remaining{monitor_name="New",monitor_type="http",monitor_url="https://new.example.com",monitor_hostname="",monitor_port=""} 40
PROM;

    public function test_viewer_can_read_status_and_cannot_store_the_key(): void
    {
        Sanctum::actingAs(User::factory()->viewer()->create());

        $this->getJson('/api/v1/integrations/uptime-kuma')
            ->assertOk()
            ->assertJsonPath('data.api_key_set', false);

        $this->putJson('/api/v1/integrations/uptime-kuma', [
            'base_url' => 'https://kuma.test',
            'api_key' => self::KEY,
        ])->assertForbidden();
    }

    public function test_admin_stores_the_key_encrypted_and_never_reads_it_back(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());

        $saved = $this->putJson('/api/v1/integrations/uptime-kuma', [
            'base_url' => 'https://kuma.test/metrics',
            'api_key' => self::KEY,
        ])->assertOk();

        $saved->assertJsonPath('data.base_url', 'https://kuma.test')
            ->assertJsonPath('data.api_key_set', true)
            ->assertJsonPath('data.api_key_saved', true);
        $this->assertStringNotContainsString(self::KEY, $saved->getContent());

        $row = Setting::query()->find(PlatformSettings::UPTIME_KUMA_API_KEY);
        $this->assertNotNull($row);
        $this->assertTrue($row->encrypted);
        $this->assertStringNotContainsString(self::KEY, (string) $row->value);
        $this->assertSame(self::KEY, PlatformSettings::get(PlatformSettings::UPTIME_KUMA_API_KEY));

        $this->putJson('/api/v1/integrations/uptime-kuma', [
            'base_url' => 'https://user:secret@kuma.test',
        ])->assertUnprocessable();

        $this->putJson('/api/v1/integrations/uptime-kuma', [
            'api_key' => '',
        ])->assertOk()->assertJsonPath('data.api_key_set', false);

        $this->assertNull(PlatformSettings::get(PlatformSettings::UPTIME_KUMA_API_KEY));
    }

    public function test_sync_updates_matches_and_can_create_the_rest(): void
    {
        Sanctum::actingAs(User::factory()->manager()->create());

        PlatformSettings::set(PlatformSettings::UPTIME_KUMA_BASE_URL, 'https://kuma.test');
        PlatformSettings::set(PlatformSettings::UPTIME_KUMA_API_KEY, self::KEY);

        $mail = Endpoint::create([
            'name' => 'Mail',
            'url' => 'https://mail.example.com/',
            'source' => EndpointSource::Webhook,
            'expires_at' => now()->addYear()->toDateString(),
        ]);
        $mail->forceFill(['sent_offsets' => [30]])->save();

        Endpoint::create([
            'name' => 'VPN cert',
            'hostname' => 'vpn.example.com',
            'expires_at' => now()->addYear()->toDateString(),
        ]);

        Http::fake([
            'https://kuma.test/metrics' => Http::response(self::METRICS, 200),
        ]);

        $first = $this->postJson('/api/v1/integrations/uptime-kuma/sync')
            ->assertOk();

        $first->assertJsonPath('data.matched', 2)
            ->assertJsonPath('data.created', 0)
            ->assertJsonPath('data.unmatched_count', 1);

        $mail->refresh();
        $this->assertSame(now()->addDays(14)->toDateString(), $mail->expires_at->toDateString());
        $this->assertSame([], $mail->sent_offsets);
        $this->assertSame(EndpointSource::Webhook, $mail->source);

        $vpn = Endpoint::query()->where('hostname', 'vpn.example.com')->firstOrFail();
        $this->assertSame(now()->addDays(3)->toDateString(), $vpn->expires_at->toDateString());
        $this->assertSame(EndpointSource::UptimeKuma, $vpn->source);

        $this->postJson('/api/v1/integrations/uptime-kuma/sync', ['create_missing' => true])
            ->assertOk()
            ->assertJsonPath('data.created', 1);

        $created = Endpoint::query()->where('name', 'New')->firstOrFail();
        $this->assertSame(EndpointSource::UptimeKuma, $created->source);
        $this->assertSame('https://new.example.com', $created->url);
        $this->assertNotNull($created->webhook_token);

        Http::assertSent(function ($request): bool {
            return $request->url() === 'https://kuma.test/metrics'
                && $request->hasHeader('Authorization', 'Basic '.base64_encode('openmsp:'.self::KEY));
        });
    }

    public function test_rejected_key_is_a_validation_error(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());
        PlatformSettings::set(PlatformSettings::UPTIME_KUMA_BASE_URL, 'https://kuma.test');
        PlatformSettings::set(PlatformSettings::UPTIME_KUMA_API_KEY, self::KEY);

        Http::fake([
            'https://kuma.test/metrics' => Http::response('no', 401),
        ]);

        $this->postJson('/api/v1/integrations/uptime-kuma/test')
            ->assertUnprocessable();

        $this->assertStringContainsString('rejected the API key', (string) PlatformSettings::get(PlatformSettings::UPTIME_KUMA_LAST_ERROR));
    }

    public function test_command_skips_when_unconfigured_and_fails_on_auth(): void
    {
        $this->artisan('uptime-kuma:sync')->assertSuccessful();

        PlatformSettings::set(PlatformSettings::UPTIME_KUMA_BASE_URL, 'https://kuma.test');
        PlatformSettings::set(PlatformSettings::UPTIME_KUMA_API_KEY, self::KEY);
        Http::fake([
            'https://kuma.test/metrics' => Http::response('no', 401),
        ]);

        $this->artisan('uptime-kuma:sync')->assertFailed();
    }

    public function test_admin_stores_the_dashboard_login_encrypted_and_never_reads_it_back(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());

        $saved = $this->putJson('/api/v1/integrations/uptime-kuma', [
            'username' => 'kuma',
            'password' => 'kuma-secret',
        ])->assertOk();

        $saved->assertJsonPath('data.username', 'kuma')
            ->assertJsonPath('data.password_set', true)
            ->assertJsonPath('data.password_saved', true);
        $this->assertStringNotContainsString('kuma-secret', $saved->getContent());

        $row = Setting::query()->find(PlatformSettings::UPTIME_KUMA_PASSWORD);
        $this->assertNotNull($row);
        $this->assertTrue($row->encrypted);
        $this->assertStringNotContainsString('kuma-secret', (string) $row->value);
        $this->assertSame('kuma-secret', PlatformSettings::get(PlatformSettings::UPTIME_KUMA_PASSWORD));

        $this->putJson('/api/v1/integrations/uptime-kuma', [
            'base_url' => 'https://kuma.test',
        ])->assertOk()->assertJsonPath('data.password_set', true);

        $this->putJson('/api/v1/integrations/uptime-kuma', [
            'password' => '',
            'username' => '',
        ])->assertOk()
            ->assertJsonPath('data.password_set', false)
            ->assertJsonPath('data.username', null);

        $this->assertNull(PlatformSettings::get(PlatformSettings::UPTIME_KUMA_PASSWORD));
        $this->assertNull(PlatformSettings::get(PlatformSettings::UPTIME_KUMA_USERNAME));
    }

    public function test_push_adds_the_webhook_and_turns_expiry_notifications_off(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());
        $this->configureLogin();
        $endpoint = Endpoint::create([
            'name' => 'Mail',
            'url' => 'https://mail.example.com/',
        ]);

        $this->fakeKuma($this->handshakeThen(
            $this->loginPoll([
                '3' => [
                    'id' => 3,
                    'name' => 'Mail',
                    'url' => 'https://mail.example.com',
                ],
            ], []),
            'ok',
            '431'.json_encode([['ok' => true, 'id' => 9]]),
            'ok',
            '432'.json_encode([[
                'ok' => true,
                'monitor' => [
                    'id' => 3,
                    'name' => 'Mail',
                    'url' => 'https://mail.example.com',
                    'expiryNotification' => true,
                    'domainExpiryNotification' => true,
                    'notificationIDList' => ['4' => true],
                ],
            ]]),
            'ok',
            '433'.json_encode([['ok' => true]]),
        ));

        $this->postJson('/api/v1/integrations/uptime-kuma/webhooks')
            ->assertOk()
            ->assertJsonPath('data.endpoints', 1)
            ->assertJsonPath('data.attached', 1)
            ->assertJsonPath('data.created', 1)
            ->assertJsonPath('data.updated', 0)
            ->assertJsonPath('data.unchanged', 0)
            ->assertJsonPath('data.unmatched_count', 0)
            ->assertJsonPath('data.ambiguous_count', 0);

        Http::assertSentCount(11);

        $added = $this->packet('addNotification');
        $this->assertSame('webhook', $added[1]['type']);
        $this->assertFalse($added[1]['isDefault']);
        $this->assertFalse($added[1]['applyExisting']);
        $this->assertSame('json', $added[1]['webhookContentType']);
        $this->assertSame('OpenMSP '.$endpoint->id.': Mail', $added[1]['name']);
        $this->assertStringContainsString('/hooks/endpoints/', $added[1]['webhookURL']);
        $this->assertNull($added[2]);

        $monitor = $this->packet('editMonitor')[1];
        $this->assertFalse($monitor['expiryNotification']);
        $this->assertFalse($monitor['domainExpiryNotification']);
        $this->assertTrue($monitor['notificationIDList']['4']);
        $this->assertTrue($monitor['notificationIDList']['9']);
        $this->assertStringContainsString('"notificationIDList":{', $this->rawPacket('editMonitor'));

        $login = $this->rawPacket('login');
        $this->assertStringContainsString('kuma-secret', $login);
        $this->assertStringNotContainsString(self::KEY, $login);
    }

    public function test_push_matches_a_monitor_by_hostname(): void
    {
        Sanctum::actingAs(User::factory()->manager()->create());
        $this->configureLogin();
        Endpoint::create([
            'name' => 'VPN',
            'hostname' => 'vpn.example.com',
        ]);

        $this->fakeKuma($this->handshakeThen(
            $this->loginPoll([
                '8' => [
                    'id' => 8,
                    'name' => 'VPN',
                    'url' => 'https://vpn.example.com/health',
                ],
            ], []),
            'ok',
            '431'.json_encode([['ok' => true, 'id' => 9]]),
            'ok',
            '432'.json_encode([[
                'ok' => true,
                'monitor' => [
                    'id' => 8,
                    'expiryNotification' => true,
                    'domainExpiryNotification' => false,
                    'notificationIDList' => [],
                ],
            ]]),
            'ok',
            '433'.json_encode([['ok' => true]]),
        ));

        $this->postJson('/api/v1/integrations/uptime-kuma/webhooks')
            ->assertOk()
            ->assertJsonPath('data.attached', 1)
            ->assertJsonPath('data.created', 1);

        $this->assertSame(8, $this->packet('getMonitor')[1]);
        $this->assertFalse($this->packet('editMonitor')[1]['expiryNotification']);
        $this->assertFalse($this->packet('editMonitor')[1]['domainExpiryNotification']);
    }

    public function test_push_updates_a_webhook_after_the_token_rotates(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());
        $this->configureLogin();
        $endpoint = Endpoint::create([
            'name' => 'Mail',
            'url' => 'https://mail.example.com',
        ]);
        $stale = 'https://openmsp.test/hooks/endpoints/old-token';

        $this->fakeKuma($this->handshakeThen(
            $this->loginPoll([
                '3' => ['id' => 3, 'name' => 'Mail', 'url' => 'https://mail.example.com'],
            ], [[
                'id' => 9,
                'name' => 'OpenMSP '.$endpoint->id.': Mail',
                'config' => json_encode(['webhookURL' => $stale]),
            ]]),
            'ok',
            '431'.json_encode([['ok' => true, 'id' => 9]]),
            'ok',
            '432'.json_encode([[
                'ok' => true,
                'monitor' => [
                    'id' => 3,
                    'expiryNotification' => true,
                    'domainExpiryNotification' => true,
                    'notificationIDList' => ['9' => true],
                ],
            ]]),
            'ok',
            '433'.json_encode([['ok' => true]]),
        ));

        $this->postJson('/api/v1/integrations/uptime-kuma/webhooks', ['endpoint_id' => $endpoint->id])
            ->assertOk()
            ->assertJsonPath('data.endpoints', 1)
            ->assertJsonPath('data.created', 0)
            ->assertJsonPath('data.updated', 1)
            ->assertJsonPath('data.attached', 1);

        $updated = $this->packet('addNotification');
        $this->assertSame(9, $updated[2]);
        $this->assertSame($endpoint->webhookUrl(), $updated[1]['webhookURL']);
        $this->assertStringContainsString('/hooks/endpoints/', $updated[1]['webhookURL']);
    }

    public function test_push_leaves_a_monitor_alone_when_the_webhook_is_already_attached(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());
        $this->configureLogin();
        $endpoint = Endpoint::create([
            'name' => 'Mail',
            'url' => 'https://mail.example.com',
        ]);

        $this->fakeKuma($this->handshakeThen(
            $this->loginPoll([
                '3' => ['id' => 3, 'name' => 'Mail', 'url' => 'https://mail.example.com'],
            ], [[
                'id' => 9,
                'name' => 'Something else',
                'config' => json_encode(['webhookURL' => $endpoint->webhookUrl()]),
            ]]),
            'ok',
            '431'.json_encode([[
                'ok' => true,
                'monitor' => [
                    'id' => 3,
                    'expiryNotification' => false,
                    'domainExpiryNotification' => false,
                    'notificationIDList' => ['9' => true],
                ],
            ]]),
        ));

        $this->postJson('/api/v1/integrations/uptime-kuma/webhooks')
            ->assertOk()
            ->assertJsonPath('data.created', 0)
            ->assertJsonPath('data.updated', 0)
            ->assertJsonPath('data.attached', 0)
            ->assertJsonPath('data.unchanged', 1);

        Http::assertSentCount(7);
        $this->assertSame([], $this->packets('addNotification'));
        $this->assertSame([], $this->packets('editMonitor'));
    }

    public function test_push_skips_an_ambiguous_monitor(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());
        $this->configureLogin();
        Endpoint::create([
            'name' => 'Mail',
            'url' => 'https://mail.example.com',
        ]);

        $this->fakeKuma($this->handshakeThen(
            $this->loginPoll([
                '3' => ['id' => 3, 'name' => 'Mail A', 'url' => 'https://mail.example.com'],
                '4' => ['id' => 4, 'name' => 'Mail B', 'url' => 'https://mail.example.com/'],
            ], []),
            'ok',
            '431'.json_encode([['ok' => true, 'id' => 9]]),
        ));

        $this->postJson('/api/v1/integrations/uptime-kuma/webhooks')
            ->assertOk()
            ->assertJsonPath('data.created', 1)
            ->assertJsonPath('data.attached', 0)
            ->assertJsonPath('data.ambiguous_count', 1)
            ->assertJsonPath('data.ambiguous.0', 'Mail');

        $this->assertSame([], $this->packets('editMonitor'));
    }

    public function test_push_without_a_login_explains_that_the_api_key_is_not_enough(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());
        PlatformSettings::set(PlatformSettings::UPTIME_KUMA_BASE_URL, 'https://kuma.test');
        PlatformSettings::set(PlatformSettings::UPTIME_KUMA_API_KEY, self::KEY);
        PlatformSettings::set(PlatformSettings::UPTIME_KUMA_LAST_ERROR, 'previous scrape');

        Http::fake();

        $this->postJson('/api/v1/integrations/uptime-kuma/webhooks')
            ->assertUnprocessable()
            ->assertJsonPath(
                'errors.uptime_kuma.0',
                'Set the Uptime Kuma base URL, username and password. The API key only reads /metrics and cannot add a webhook.',
            );

        Http::assertNothingSent();
        $this->assertSame('previous scrape', PlatformSettings::get(PlatformSettings::UPTIME_KUMA_LAST_ERROR));
    }

    public function test_push_refuses_two_factor(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());
        $this->configureLogin();
        PlatformSettings::set(PlatformSettings::UPTIME_KUMA_LAST_ERROR, 'previous scrape');
        $this->fakeKuma($this->handshakeThen(
            '430'.json_encode([['ok' => false, 'tokenRequired' => true]]),
        ));

        $this->postJson('/api/v1/integrations/uptime-kuma/webhooks')
            ->assertUnprocessable()
            ->assertJsonPath(
                'errors.uptime_kuma.0',
                'Uptime Kuma asked for a two-factor code. Use a login without two-factor authentication.',
            );

        $this->assertSame('previous scrape', PlatformSettings::get(PlatformSettings::UPTIME_KUMA_LAST_ERROR));
    }

    public function test_sales_cannot_push_webhooks(): void
    {
        Sanctum::actingAs(User::factory()->sales()->create());
        $this->configureLogin();

        $this->postJson('/api/v1/integrations/uptime-kuma/webhooks')->assertForbidden();
    }

    private function configureLogin(): void
    {
        PlatformSettings::set(PlatformSettings::UPTIME_KUMA_BASE_URL, 'https://kuma.test');
        PlatformSettings::set(PlatformSettings::UPTIME_KUMA_API_KEY, self::KEY);
        PlatformSettings::set(PlatformSettings::UPTIME_KUMA_USERNAME, 'kuma');
        PlatformSettings::set(PlatformSettings::UPTIME_KUMA_PASSWORD, 'kuma-secret');
    }

    /**
     * @param  list<string>  $bodies
     */
    private function fakeKuma(array $bodies): void
    {
        Http::preventStrayRequests();
        $sequence = Http::sequence();
        foreach ($bodies as $body) {
            $sequence->push($body, 200, ['Content-Type' => 'text/plain']);
        }

        Http::fake([
            'https://kuma.test/socket.io/*' => $sequence,
        ]);
    }

    /**
     * @param  array<int|string, mixed>  $monitors
     * @param  list<array<string, mixed>>  $notifications
     */
    private function loginPoll(array $monitors, array $notifications): string
    {
        return implode("\x1e", [
            '430'.json_encode([['ok' => true]]),
            '42'.json_encode(['monitorList', $monitors]),
            '42'.json_encode(['notificationList', $notifications]),
        ]);
    }

    /**
     * @return list<string>
     */
    private function handshakeThen(string ...$after): array
    {
        return [
            '0'.json_encode(['sid' => 'abc']),
            'ok',
            '40',
            'ok',
            ...$after,
        ];
    }

    /**
     * @return list<array<int, mixed>>
     */
    private function packets(string $event): array
    {
        $found = [];
        foreach (Http::recorded() as $pair) {
            $body = (string) $pair[0]->body();
            if (! str_starts_with($body, '42')) {
                continue;
            }

            $decoded = json_decode((string) preg_replace('/^42\d+/', '', $body), true);
            if (is_array($decoded) && ($decoded[0] ?? null) === $event) {
                $found[] = $decoded;
            }
        }

        return $found;
    }

    /**
     * @return array<int, mixed>
     */
    private function packet(string $event): array
    {
        $found = $this->packets($event);
        $this->assertCount(1, $found, 'Expected one '.$event.' packet.');

        return $found[0];
    }

    private function rawPacket(string $event): string
    {
        foreach (Http::recorded() as $pair) {
            $body = (string) $pair[0]->body();
            if (str_starts_with($body, '42') && str_contains($body, '"'.$event.'"')) {
                return $body;
            }
        }

        $this->fail('No raw '.$event.' packet was sent.');
    }
}
