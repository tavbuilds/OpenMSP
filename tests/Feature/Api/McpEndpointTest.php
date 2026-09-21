<?php

namespace Tests\Feature\Api;

use App\Models\Company;
use App\Models\User;
use App\Support\PlatformSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class McpEndpointTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_mcp_is_rejected(): void
    {
        $this->postJson('/mcp', [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'initialize',
            'params' => ['protocolVersion' => '2025-03-26', 'capabilities' => [], 'clientInfo' => ['name' => 'test', 'version' => '1']],
        ])->assertUnauthorized();
    }

    public function test_options_is_allowed_without_auth(): void
    {
        $this->call('OPTIONS', '/mcp')
            ->assertNoContent()
            ->assertHeader('Access-Control-Allow-Origin', '*');
    }

    public function test_initialize_and_list_tools(): void
    {
        Sanctum::actingAs(User::factory()->viewer()->create());

        $init = $this->postJson('/mcp', [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'initialize',
            'params' => [
                'protocolVersion' => '2025-03-26',
                'capabilities' => [],
                'clientInfo' => ['name' => 'phpunit', 'version' => '1'],
            ],
        ])->assertOk();

        $init->assertJsonPath('result.protocolVersion', '2025-03-26');
        $init->assertJsonPath('result.serverInfo.name', 'openmsp');
        $this->assertArrayHasKey('tools', $init->json('result.capabilities'));

        $list = $this->postJson('/mcp', [
            'jsonrpc' => '2.0',
            'id' => 2,
            'method' => 'tools/list',
        ])->assertOk();

        $names = collect($list->json('result.tools'))->pluck('name');
        $this->assertTrue($names->contains('get_dashboard'));
        $this->assertTrue($names->contains('list_companies'));
        $this->assertTrue($names->contains('create_planned_task'));
        $this->assertGreaterThan(30, $names->count());
    }

    public function test_initialized_notification_returns_accepted(): void
    {
        Sanctum::actingAs(User::factory()->viewer()->create());

        $this->postJson('/mcp', [
            'jsonrpc' => '2.0',
            'method' => 'notifications/initialized',
        ])->assertStatus(202);
    }

    public function test_viewer_can_call_dashboard_and_cannot_create_company(): void
    {
        Sanctum::actingAs(User::factory()->viewer()->create());
        Company::create(['name' => 'Viewer Co', 'country' => 'NL']);

        $dash = $this->postJson('/mcp', [
            'jsonrpc' => '2.0',
            'id' => 3,
            'method' => 'tools/call',
            'params' => ['name' => 'get_dashboard', 'arguments' => []],
        ])->assertOk();

        $this->assertFalse($dash->json('result.isError'));
        $this->assertStringContainsString('active_contracts_count', $dash->json('result.content.0.text'));

        $create = $this->postJson('/mcp', [
            'jsonrpc' => '2.0',
            'id' => 4,
            'method' => 'tools/call',
            'params' => [
                'name' => 'create_company',
                'arguments' => ['name' => 'Nope'],
            ],
        ])->assertOk();

        $this->assertTrue($create->json('result.isError'));
        $this->assertStringContainsString('403', $create->json('result.content.0.text'));
        $this->assertFalse(Company::query()->where('name', 'Nope')->exists());
    }

    public function test_sales_can_create_company_via_mcp_with_bearer_token(): void
    {
        $sales = User::factory()->sales()->create();
        $token = $sales->createToken('grok')->plainTextToken;

        $response = $this->postJson('/mcp', [
            'jsonrpc' => '2.0',
            'id' => 5,
            'method' => 'tools/call',
            'params' => [
                'name' => 'create_company',
                'arguments' => ['name' => 'Acme BV', 'country' => 'NL'],
            ],
        ], [
            'Authorization' => 'Bearer '.$token,
        ])->assertOk();

        $this->assertFalse($response->json('result.isError'));
        $this->assertTrue(Company::query()->where('name', 'Acme BV')->exists());
        $this->assertStringContainsString('Acme BV', $response->json('result.content.0.text'));
    }

    public function test_unknown_tool_is_jsonrpc_error(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->postJson('/mcp', [
            'jsonrpc' => '2.0',
            'id' => 6,
            'method' => 'tools/call',
            'params' => ['name' => 'invent_invoices', 'arguments' => []],
        ])
            ->assertOk()
            ->assertJsonPath('error.code', -32602);
    }

    public function test_admin_mcp_page_renders(): void
    {
        $admin = User::factory()->admin()->create();
        PlatformSettings::markOnboardingComplete();

        $this->actingAs($admin)
            ->get('/admin/mcp')
            ->assertOk()
            ->assertSee('/mcp', false)
            ->assertSee('get_dashboard', false);
    }

    public function test_api_tokens_page_shows_mcp_endpoint(): void
    {
        $admin = User::factory()->admin()->create();
        PlatformSettings::markOnboardingComplete();

        $this->actingAs($admin)
            ->get('/admin/api-tokens')
            ->assertOk()
            ->assertSee('/mcp', false)
            ->assertSee(__('MCP endpoint'), false);
    }
}
