<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use App\Support\UserAdministration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserAdministrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_last_admin_cannot_be_deleted_or_demoted(): void
    {
        $admin = User::factory()->admin()->create();
        $viewer = User::factory()->viewer()->create();

        $this->assertTrue(UserAdministration::isLastAdmin($admin));
        $this->assertFalse(UserAdministration::canDelete($admin, $admin));
        $this->assertFalse(UserAdministration::canDelete($viewer, $admin));
        $this->assertFalse(UserAdministration::canChangeRole($admin, UserRole::Viewer));

        $second = User::factory()->admin()->create();
        $this->assertTrue(UserAdministration::canDelete($admin, $second));
        $this->assertTrue(UserAdministration::canChangeRole($admin, UserRole::Manager));
    }

    public function test_reset_password_revokes_tokens(): void
    {
        $user = User::factory()->admin()->create();
        $user->createToken('agent');
        $this->assertSame(1, $user->tokens()->count());

        UserAdministration::resetPassword($user, 'new-secret-password');

        $this->assertSame(0, $user->fresh()->tokens()->count());
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('new-secret-password', $user->fresh()->password));
    }

    public function test_create_and_revoke_all_tokens(): void
    {
        $a = User::factory()->admin()->create();
        $b = User::factory()->viewer()->create();

        $plain = UserAdministration::createToken($a, 'mcp');
        $this->assertNotSame('', $plain);
        UserAdministration::createToken($b, 'other');

        $this->assertSame(2, UserAdministration::revokeAllTokens());
        $this->assertSame(0, $a->fresh()->tokens()->count());
        $this->assertSame(0, $b->fresh()->tokens()->count());
    }
}
