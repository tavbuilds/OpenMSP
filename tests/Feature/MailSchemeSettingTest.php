<?php

namespace Tests\Feature;

use App\Filament\Pages\ManageSettings;
use App\Models\User;
use App\Support\PlatformSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The encryption field was free text labelled MAIL_SCHEME, so an operator
 * typed "SSL" — the name every mail provider uses — and every send failed
 * with a scheme error. The field is a Select now; these pin the round trip.
 */
class MailSchemeSettingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->admin()->create());
    }

    public function test_a_legacy_ssl_value_opens_on_the_matching_option(): void
    {
        PlatformSettings::set(PlatformSettings::MAIL_SCHEME, 'SSL');

        Livewire::test(ManageSettings::class)
            ->assertSetStrict('data.mail_scheme', 'smtps');
    }

    /** Null would miss the empty option and leave the Select blank. */
    public function test_an_unset_scheme_opens_on_the_environment_option(): void
    {
        config(['mail.mailers.smtp.scheme' => null]);

        Livewire::test(ManageSettings::class)
            ->assertSetStrict('data.mail_scheme', '');
    }

    public function test_saving_stores_a_scheme_symfony_accepts(): void
    {
        Livewire::test(ManageSettings::class)
            ->set('data.mail_mailer', 'smtp')
            ->set('data.mail_scheme', 'smtps')
            ->call('save');

        $this->assertSame('smtps', PlatformSettings::get(PlatformSettings::MAIL_SCHEME));
    }

    public function test_choosing_the_environment_option_clears_the_stored_scheme(): void
    {
        PlatformSettings::set(PlatformSettings::MAIL_SCHEME, 'smtps');

        Livewire::test(ManageSettings::class)
            ->set('data.mail_mailer', 'smtp')
            ->set('data.mail_scheme', '')
            ->call('save');

        $this->assertNull(PlatformSettings::get(PlatformSettings::MAIL_SCHEME));
    }
}
