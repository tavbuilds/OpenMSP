<?php

namespace Tests\Unit;

use App\Support\LocaleCatalog;
use PHPUnit\Framework\TestCase;

class LocaleCatalogTest extends TestCase
{
    public function test_stored_choice_beats_browser_and_platform(): void
    {
        $this->assertSame('sv', LocaleCatalog::detect(
            stored: 'sv',
            acceptLanguage: 'de-DE,de;q=0.9',
            platformDefault: 'nl',
        ));
    }

    public function test_accept_language_beats_platform_default(): void
    {
        $this->assertSame('de', LocaleCatalog::detect(
            stored: null,
            acceptLanguage: 'de-DE,de;q=0.8,en;q=0.5',
            platformDefault: 'nl',
        ));
    }

    public function test_portuguese_brazil_maps_to_pt(): void
    {
        $this->assertSame('pt', LocaleCatalog::detect(
            navigatorLanguages: ['pt-BR', 'pt'],
        ));
    }

    public function test_unsupported_language_falls_back_to_english(): void
    {
        $this->assertSame('en', LocaleCatalog::detect(
            acceptLanguage: 'zh-CN,zh;q=0.9',
            platformDefault: 'zh',
        ));
    }

    public function test_platform_default_when_browser_unknown(): void
    {
        $this->assertSame('nl', LocaleCatalog::detect(
            acceptLanguage: 'zh-CN',
            platformDefault: 'nl',
        ));
    }

    public function test_match_accepts_bcp47_tags(): void
    {
        $this->assertSame('nl', LocaleCatalog::match('nl-NL'));
        $this->assertSame('en', LocaleCatalog::match('en_GB'));
        $this->assertNull(LocaleCatalog::match('zh-CN'));
    }
}
