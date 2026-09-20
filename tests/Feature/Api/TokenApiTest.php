<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TokenApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_requests_are_rejected(): void
    {
        $this->getJson('/api/v1/tokens')->assertUnauthorized();
        $this->postJson('/api/v1/tokens', ['name' => 'x'])->assertUnauthorized();
    }

    public function test_user_can_list_create_and_revoke_own_tokens(): void
    {
        $user = User::factory()->viewer()->create();
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/tokens')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $create = $this->postJson('/api/v1/tokens', [
            'name' => 'ci-agent',
            'abilities' => ['*'],
        ]);

        $create->assertCreated()
            ->assertJsonPath('data.name', 'ci-agent')
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'name',
                    'abilities',
                    'last_used_at',
                    'created_at',
                    'plain_text_token',
                ],
            ]);

        $plain = $create->json('data.plain_text_token');
        $this->assertIsString($plain);
        $this->assertNotSame('', $plain);

        $id = $create->json('data.id');

        $list = $this->getJson('/api/v1/tokens')->assertOk();
        $list->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $id)
            ->assertJsonPath('data.0.name', 'ci-agent');

        $this->assertArrayNotHasKey('plain_text_token', $list->json('data.0'));
        $this->assertArrayNotHasKey('token', $list->json('data.0'));

        $this->deleteJson("/api/v1/tokens/{$id}")
            ->assertNoContent();

        $this->getJson('/api/v1/tokens')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_user_cannot_revoke_another_users_token(): void
    {
        $owner = User::factory()->admin()->create();
        $other = User::factory()->admin()->create();

        $foreign = $owner->createToken('secret')->accessToken;

        Sanctum::actingAs($other);

        $this->deleteJson("/api/v1/tokens/{$foreign->id}")
            ->assertNotFound();

        $this->assertDatabaseHas('personal_access_tokens', [
            'id' => $foreign->id,
            'tokenable_id' => $owner->id,
        ]);
    }

    public function test_create_defaults_abilities_to_star(): void
    {
        Sanctum::actingAs(User::factory()->sales()->create());

        $this->postJson('/api/v1/tokens', ['name' => 'default-abilities'])
            ->assertCreated()
            ->assertJsonPath('data.abilities', ['*']);
    }
}
