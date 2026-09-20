<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\PlatformSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OnboardingFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_empty_install_sends_guests_to_account_setup(): void
    {
        $response = $this->get('/admin');

        $response->assertRedirect();
        $this->assertTrue(
            str_contains($response->headers->get('Location'), 'register')
            || str_contains($response->headers->get('Location'), 'setup')
            || str_contains($response->headers->get('Location'), 'login'),
        );
    }

    public function test_authenticated_admin_is_sent_to_onboarding_until_complete(): void
    {
        $admin = User::factory()->admin()->create();
        $this->assertFalse(PlatformSettings::onboardingCompleted());

        $this->actingAs($admin, 'web')
            ->get('/admin')
            ->assertRedirect();

        $location = $this->actingAs($admin, 'web')->get('/admin')->headers->get('Location');
        $this->assertNotNull($location);
        $this->assertTrue(
            str_contains($location, 'setup') || str_contains($location, 'onboarding'),
            "Expected onboarding redirect, got {$location}"
        );

        PlatformSettings::markOnboardingComplete();

        $after = $this->actingAs($admin, 'web')->get('/admin');
        $afterLocation = $after->headers->get('Location') ?? '';
        $this->assertFalse(
            str_contains($afterLocation, 'setup'),
            "Onboarding should be done, still redirecting to {$afterLocation}"
        );
        if (! $after->isRedirect()) {
            $after->assertSuccessful();
        }
    }

    public function test_viewer_is_not_forced_through_onboarding(): void
    {
        User::factory()->admin()->create();
        $viewer = User::factory()->viewer()->create();

        $this->assertFalse(PlatformSettings::onboardingCompleted());

        $response = $this->actingAs($viewer, 'web')->get('/admin');
        $location = $response->headers->get('Location') ?? '';
        $this->assertFalse(
            str_contains($location, 'setup'),
            "Viewer should not be sent to onboarding, got {$location}"
        );
    }

    public function test_register_is_closed_once_a_user_exists(): void
    {
        User::factory()->admin()->create();

        $this->get('/admin/register')->assertRedirect();

        $this->from('/admin/register')->post('/admin/register', [
            'name' => 'Intruder',
            'email' => 'intruder@example.com',
            'password' => 'password-password',
            'passwordConfirmation' => 'password-password',
        ]);

        $this->assertDatabaseMissing('users', ['email' => 'intruder@example.com']);
        $this->assertSame(1, User::query()->count());
    }

    public function test_settings_page_is_forbidden_for_viewers(): void
    {
        PlatformSettings::markOnboardingComplete();
        $viewer = User::factory()->viewer()->create();

        $this->actingAs($viewer, 'web')
            ->get('/admin/settings')
            ->assertForbidden();
    }

    public function test_admin_can_open_setup_page_before_onboarding_is_complete(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin, 'web')
            ->get('/admin/setup')
            ->assertSuccessful();
    }
}
