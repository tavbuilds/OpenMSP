<?php

namespace Tests\Unit;

use App\Models\Setting;
use App\Support\PlatformSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlatformSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_name_falls_back_to_msp_platform(): void
    {
        config(['app.name' => 'Laravel']);

        $this->assertSame('MSP Platform', PlatformSettings::name());
    }

    public function test_set_get_and_forget(): void
    {
        PlatformSettings::set(PlatformSettings::NAME, 'Acme MSP');

        $this->assertSame('Acme MSP', PlatformSettings::name());
        $this->assertSame('Acme MSP', PlatformSettings::get(PlatformSettings::NAME));

        PlatformSettings::forget(PlatformSettings::NAME);
        config(['app.name' => 'Laravel']);

        $this->assertSame('MSP Platform', PlatformSettings::name());
    }

    public function test_secrets_are_encrypted_at_rest(): void
    {
        PlatformSettings::set(PlatformSettings::STRIPE_SECRET, 'sk_test_secret');

        $row = Setting::query()->find(PlatformSettings::STRIPE_SECRET);
        $this->assertTrue($row->encrypted);
        $this->assertNotSame('sk_test_secret', $row->value);
        $this->assertSame('sk_test_secret', PlatformSettings::get(PlatformSettings::STRIPE_SECRET));
    }

    public function test_apply_to_config_overlays_env(): void
    {
        config(['app.name' => 'Env Name', 'services.stripe.secret' => 'sk_env']);

        PlatformSettings::set(PlatformSettings::NAME, 'UI Name');
        PlatformSettings::set(PlatformSettings::STRIPE_SECRET, 'sk_ui');
        PlatformSettings::applyToConfig();

        $this->assertSame('UI Name', config('app.name'));
        $this->assertSame('sk_ui', config('services.stripe.secret'));
    }

    public function test_onboarding_flag(): void
    {
        $this->assertFalse(PlatformSettings::onboardingCompleted());
        PlatformSettings::markOnboardingComplete();
        $this->assertTrue(PlatformSettings::onboardingCompleted());
        PlatformSettings::resetOnboarding();
        $this->assertFalse(PlatformSettings::onboardingCompleted());
    }

    public function test_require_mfa_toggle(): void
    {
        $this->assertFalse(PlatformSettings::requireMfa());
        PlatformSettings::set(PlatformSettings::REQUIRE_MFA, true);
        $this->assertTrue(PlatformSettings::requireMfa());
        PlatformSettings::set(PlatformSettings::REQUIRE_MFA, false);
        $this->assertFalse(PlatformSettings::requireMfa());
    }

    public function test_primary_color_normalizes_hex(): void
    {
        $this->assertSame('#2563eb', PlatformSettings::primaryColor());
        PlatformSettings::set(PlatformSettings::PRIMARY_COLOR, '7f1d1d');
        $this->assertSame('#7f1d1d', PlatformSettings::primaryColor());
        PlatformSettings::set(PlatformSettings::PRIMARY_COLOR, 'not-a-color');
        $this->assertSame('#2563eb', PlatformSettings::primaryColor());
    }
}
