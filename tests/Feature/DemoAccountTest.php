<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\DemoAccount;
use App\Support\DemoData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class DemoAccountTest extends TestCase
{
    use RefreshDatabase;

    public function test_username_alias_maps_to_demo_email(): void
    {
        $this->assertSame(DemoAccount::EMAIL, DemoAccount::resolveLoginEmail('test'));
        $this->assertSame(DemoAccount::EMAIL, DemoAccount::resolveLoginEmail('DEMO'));
        $this->assertSame('admin@example.com', DemoAccount::resolveLoginEmail('admin@example.com'));
    }

    public function test_login_page_accepts_the_demo_username_field(): void
    {
        DemoAccount::ensure();

        $this->get('/admin/login')
            ->assertSuccessful()
            ->assertDontSee('type="email"', false);
    }


    public function test_demo_user_is_a_read_only_viewer(): void
    {
        $user = DemoAccount::ensure();
        DemoData::seed();

        $this->assertTrue($user->is_demo);
        $this->assertFalse($user->canManageContracts());
        $this->assertFalse($user->canAdminister());
        $this->assertTrue(Auth::attempt([
            'email' => DemoAccount::resolveLoginEmail('test'),
            'password' => DemoAccount::PASSWORD,
        ]));
    }

    public function test_demo_user_does_not_close_registration(): void
    {
        DemoAccount::ensure();

        $this->assertFalse(DemoAccount::hasRealOperator());
        $this->get('/admin/register')->assertSuccessful();
    }

    public function test_creating_a_real_user_removes_the_demo_account(): void
    {
        DemoAccount::ensure();
        $this->assertTrue(DemoAccount::exists());

        User::factory()->admin()->create(['email' => 'owner@example.com']);

        $this->assertFalse(DemoAccount::exists());
        $this->assertTrue(DemoAccount::hasRealOperator());
        $this->assertDatabaseMissing('users', ['email' => DemoAccount::EMAIL]);
        $this->get('/admin/register')->assertRedirect();
    }

    public function test_purging_demo_data_also_removes_the_demo_user(): void
    {
        DemoAccount::ensure();
        DemoData::seed();
        DemoData::purge();

        $this->assertFalse(DemoAccount::exists());
        $this->assertFalse(DemoData::exists());
    }
}
