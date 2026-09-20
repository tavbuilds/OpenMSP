<?php

namespace Tests\Feature;

use App\Support\LocaleCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocaleSwitchTest extends TestCase
{
    use RefreshDatabase;

    public function test_accept_language_sets_locale_on_login(): void
    {
        $this->withHeaders(['Accept-Language' => 'de-DE,de;q=0.9,en;q=0.5'])
            ->get('/admin/login');

        $this->assertSame('de', app()->getLocale());
    }

    public function test_locale_route_sets_cookie_and_rejects_unknown_codes(): void
    {
        $this->from('/admin/login')->get('/locale/fr')
            ->assertRedirect('/admin/login')
            ->assertCookie(LocaleCatalog::COOKIE, 'fr');

        $this->get('/locale/zh')->assertNotFound();
    }

    public function test_cookie_beats_accept_language(): void
    {
        $this->withCookie(LocaleCatalog::COOKIE, 'sv')
            ->withHeaders(['Accept-Language' => 'de'])
            ->get('/admin/login');

        $this->assertSame('sv', app()->getLocale());
    }

    public function test_fallback_is_english(): void
    {
        $this->withHeaders(['Accept-Language' => 'ja,zh;q=0.8'])
            ->get('/admin/login');

        $this->assertSame('en', app()->getLocale());
    }
}
