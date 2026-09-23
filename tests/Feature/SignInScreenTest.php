<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\PlatformSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The panel has no build step, so its hand-written stylesheet is injected
 * through a render hook. If that hook ever stops reaching the signed-out
 * screens, they lose their styling silently — nothing else would notice.
 */
class SignInScreenTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        User::factory()->admin()->create();
        PlatformSettings::markOnboardingComplete();
    }

    public function test_the_panel_stylesheet_reaches_the_signed_out_screens(): void
    {
        $this->get('/admin/login')
            ->assertOk()
            // The two-factor challenge renders in this same layout: its six
            // code boxes are a narrow row that has to be centred by hand.
            ->assertSee('.fi-simple-layout .fi-one-time-code-input-ctn', false)
            ->assertSee('margin-inline: auto', false)
            // And the language picker, which is the other thing only this
            // stylesheet makes presentable.
            ->assertSee('.omsp-locale', false);
    }
}
