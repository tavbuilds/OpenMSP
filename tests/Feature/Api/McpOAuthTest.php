<?php

namespace Tests\Feature\Api;

use App\Models\OAuthAuthCode;
use App\Models\User;
use App\OAuth\McpOAuth;
use App\Support\PlatformSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class McpOAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorization_server_metadata_is_public(): void
    {
        $this->getJson('/.well-known/oauth-authorization-server')
            ->assertOk()
            ->assertJsonPath('authorization_endpoint', McpOAuth::authorizationEndpoint())
            ->assertJsonPath('token_endpoint', McpOAuth::tokenEndpoint())
            ->assertJsonPath('registration_endpoint', McpOAuth::registrationEndpoint())
            ->assertJsonFragment(['S256']);
    }

    public function test_protected_resource_metadata_and_path_variant(): void
    {
        $this->getJson('/.well-known/oauth-protected-resource')
            ->assertOk()
            ->assertJsonPath('resource', McpOAuth::resource());

        $this->getJson('/.well-known/oauth-protected-resource/mcp')
            ->assertOk()
            ->assertJsonPath('resource', McpOAuth::resource());
    }

    public function test_unauthenticated_mcp_advertises_oauth_metadata(): void
    {
        $this->postJson('/mcp', [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'initialize',
            'params' => ['protocolVersion' => '2025-03-26', 'capabilities' => [], 'clientInfo' => ['name' => 'test', 'version' => '1']],
        ])
            ->assertUnauthorized()
            ->assertHeader('WWW-Authenticate');

        $this->assertStringContainsString(
            'resource_metadata="'.McpOAuth::protectedResourceMetadataUrl().'"',
            (string) $this->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'ping'])->headers->get('WWW-Authenticate')
        );
    }

    public function test_dynamic_client_registration_stores_grok_redirect(): void
    {
        $this->postJson('/oauth/register', [
            'client_name' => 'Grok',
            'redirect_uris' => ['https://grok.com/oauth/callback'],
            'token_endpoint_auth_method' => 'none',
        ])
            ->assertCreated()
            ->assertJsonPath('token_endpoint_auth_method', 'none')
            ->assertJsonPath('redirect_uris.0', 'https://grok.com/oauth/callback');
    }

    public function test_pkce_flow_issues_token_usable_on_mcp(): void
    {
        $user = User::factory()->admin()->create();
        [$verifier, $challenge] = $this->pkce();

        $location = (string) $this->actingAs($user)
            ->post('/oauth/authorize', [
                'client_id' => 'openmsp',
                'redirect_uri' => 'https://grok.com/oauth/callback',
                'response_type' => 'code',
                'state' => 'abc',
                'code_challenge' => $challenge,
                'code_challenge_method' => 'S256',
                'scope' => 'mcp',
                'decision' => 'allow',
            ])
            ->assertRedirect()
            ->headers->get('Location');

        parse_str((string) parse_url($location, PHP_URL_QUERY), $query);
        $this->assertArrayHasKey('code', $query);
        $this->assertSame('abc', $query['state'] ?? null);

        $token = $this->post('/oauth/token', [
            'grant_type' => 'authorization_code',
            'code' => $query['code'],
            'redirect_uri' => 'https://grok.com/oauth/callback',
            'client_id' => 'openmsp',
            'code_verifier' => $verifier,
        ])->assertOk();

        $access = $token->json('access_token');
        $this->assertNotEmpty($access);
        $this->assertNotEmpty($token->json('refresh_token'));

        $this->withToken($access)
            ->postJson('/mcp', [
                'jsonrpc' => '2.0',
                'id' => 1,
                'method' => 'initialize',
                'params' => [
                    'protocolVersion' => '2025-03-26',
                    'capabilities' => [],
                    'clientInfo' => ['name' => 'grok', 'version' => '1'],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('result.serverInfo.name', 'openmsp');
    }

    public function test_wrong_pkce_verifier_is_rejected(): void
    {
        $user = User::factory()->admin()->create();
        [, $challenge] = $this->pkce();

        $this->actingAs($user)
            ->post('/oauth/authorize', [
                'client_id' => 'openmsp',
                'redirect_uri' => 'http://localhost/callback',
                'response_type' => 'code',
                'code_challenge' => $challenge,
                'code_challenge_method' => 'S256',
                'decision' => 'allow',
            ]);

        $code = OAuthAuthCode::query()->latest('created_at')->value('id');

        $this->postJson('/oauth/token', [
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => 'http://localhost/callback',
            'client_id' => 'openmsp',
            'code_verifier' => 'this-is-not-the-verifier-and-is-long-enough-to-pass-length',
        ])->assertStatus(400)->assertJsonPath('error', 'invalid_grant');
    }

    public function test_refresh_token_rotates(): void
    {
        $user = User::factory()->admin()->create();
        [$verifier, $challenge] = $this->pkce();

        $this->actingAs($user)->post('/oauth/authorize', [
            'client_id' => 'openmsp',
            'redirect_uri' => 'http://localhost/callback',
            'response_type' => 'code',
            'code_challenge' => $challenge,
            'code_challenge_method' => 'S256',
            'decision' => 'allow',
        ]);

        $code = OAuthAuthCode::query()->latest('created_at')->value('id');
        $first = $this->post('/oauth/token', [
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => 'http://localhost/callback',
            'client_id' => 'openmsp',
            'code_verifier' => $verifier,
        ])->assertOk();

        $second = $this->post('/oauth/token', [
            'grant_type' => 'refresh_token',
            'refresh_token' => $first->json('refresh_token'),
            'client_id' => 'openmsp',
        ])->assertOk();

        $this->assertNotSame($first->json('access_token'), $second->json('access_token'));

        $this->postJson('/oauth/token', [
            'grant_type' => 'refresh_token',
            'refresh_token' => $first->json('refresh_token'),
            'client_id' => 'openmsp',
        ])->assertStatus(400);
    }

    public function test_admin_pages_show_oauth_client_values(): void
    {
        $admin = User::factory()->admin()->create();
        PlatformSettings::markOnboardingComplete();

        $this->actingAs($admin)
            ->get('/admin/mcp')
            ->assertOk()
            ->assertSee('openmsp', false)
            ->assertSee('/oauth/authorize', false)
            ->assertSee('/oauth/token', false);

        $this->actingAs($admin)
            ->get('/admin/api-tokens')
            ->assertOk()
            ->assertSee('openmsp', false)
            ->assertSee('/oauth/authorize', false);
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function pkce(): array
    {
        $verifier = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');

        return [$verifier, McpOAuth::s256($verifier)];
    }
}
