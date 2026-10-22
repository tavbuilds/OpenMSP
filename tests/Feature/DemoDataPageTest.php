<?php

namespace Tests\Feature;

use App\Filament\Pages\DemoData as DemoDataPage;
use App\Filament\Pages\Passkeys;
use App\Models\User;
use App\Support\DemoData;
use App\Support\PlatformSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Retiring the page means retiring the page, not hiding the link to it.
 */
class DemoDataPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Otherwise every panel request lands on the onboarding wizard
        // instead of the page under test.
        PlatformSettings::markOnboardingComplete();
    }

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    public function test_an_administrator_can_reach_it_before_the_demo_data_is_deleted(): void
    {
        $this->actingAs($this->admin())
            ->get(DemoDataPage::getUrl())
            ->assertSuccessful();
    }

    public function test_the_url_stops_answering_once_the_demo_data_is_deleted(): void
    {
        $admin = $this->admin();
        DemoData::seed();
        DemoData::purge();

        // Not merely gone from the sidebar: typing the address, or coming
        // back to an old bookmark, has to stop working too.
        $this->actingAs($admin)
            ->get('/admin/demodata')
            ->assertForbidden();

        $this->assertFalse(DemoDataPage::canAccess());
    }

    public function test_it_is_in_the_sidebar_while_there_is_demo_data(): void
    {
        $admin = $this->admin();
        DemoData::seed();

        $this->actingAs($admin)->get('/admin')->assertSee(__('Demo data'));
    }

    /**
     * One request per state on purpose.
     *
     * Filament builds its navigation once per process, so rendering the
     * panel before and after the purge in a single test would assert
     * against a cached menu rather than against the gate.
     */
    public function test_it_is_gone_from_the_sidebar_once_the_demo_data_is_deleted(): void
    {
        $admin = $this->admin();
        DemoData::seed();
        DemoData::purge();

        $this->actingAs($admin)->get('/admin')->assertDontSee(__('Demo data'));
    }

    /** Non-administrators never had it, dismissal or not. */
    public function test_a_viewer_cannot_reach_it_either_way(): void
    {
        $viewer = User::factory()->viewer()->create();
        DemoData::seed();

        $this->actingAs($viewer)->get('/admin/demodata')->assertForbidden();
    }

    /**
     * One sidebar group, not two.
     *
     * Filament groups by the string it is handed, so a page that returns
     * the untranslated name starts a second group beside the translated
     * one — "Systeem" and "System", side by side.
     */
    public function test_every_page_in_a_group_names_it_the_same_way(): void
    {
        app()->setLocale('nl');

        $this->assertSame(__('System'), Passkeys::getNavigationGroup());
        $this->assertSame(__('System'), DemoDataPage::getNavigationGroup());
        $this->assertNotSame('System', Passkeys::getNavigationGroup());
    }
}
