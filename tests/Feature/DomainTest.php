<?php

namespace Tests\Feature;

use App\Console\Commands\SendDomainExpiryReminders;
use App\Filament\Widgets\AttentionBoard;
use App\Filament\Widgets\UpcomingDomainExpiries;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Domain;
use App\Models\User;
use App\Notifications\DomainExpiryReminder;
use App\Support\PlatformSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

class DomainTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();
        $this->company = Company::create(['name' => 'Klant BV', 'country' => 'NL']);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function domain(string $name, array $overrides = []): Domain
    {
        return Domain::query()->create(array_merge([
            'company_id' => $this->company->id,
            'name' => $name,
            'expires_at' => now()->addDays(20)->toDateString(),
            'auto_renew' => true,
        ], $overrides));
    }

    private function contact(): Contact
    {
        return Contact::query()->create([
            'company_id' => $this->company->id,
            'name' => 'Anna Bakker',
            'email' => 'anna@klant.example',
        ]);
    }

    public function test_the_name_is_normalised_and_the_extension_derived(): void
    {
        $domain = $this->domain('  KlantNaam.NL ');

        $this->assertSame('klantnaam.nl', $domain->name);
        $this->assertSame('nl', $domain->extension);
    }

    public function test_the_portal_shows_only_this_company_s_domains(): void
    {
        $this->domain('klantnaam.nl');

        $other = Company::create(['name' => 'Andere BV', 'country' => 'NL']);
        Domain::query()->create(['company_id' => $other->id, 'name' => 'andere.nl']);

        // A domain nobody has assigned yet belongs to no portal at all.
        Domain::query()->create(['name' => 'nietstoegewezen.nl']);

        $this->actingAs($this->contact(), 'portal')
            ->get(route('portal.domains'))
            ->assertOk()
            ->assertSee('klantnaam.nl')
            ->assertDontSee('andere.nl')
            ->assertDontSee('nietstoegewezen.nl');
    }

    /** A note is internal until someone deliberately shares it. */
    public function test_a_note_reaches_the_portal_only_when_it_is_shared(): void
    {
        $domain = $this->domain('klantnaam.nl', [
            'notes' => 'Renewal invoice goes to finance.',
        ]);

        $contact = $this->contact();

        $this->assertNull($domain->customerVisibleNotes());
        $this->actingAs($contact, 'portal')
            ->get(route('portal.domains'))
            ->assertOk()
            ->assertDontSee('Renewal invoice goes to finance.');

        // Sign the contact out first: the audit log records a user, and a
        // portal contact is not one.
        $this->app['auth']->guard('portal')->logout();
        $domain->forceFill(['notes_visible_to_customer' => true])->save();

        $this->assertSame('Renewal invoice goes to finance.', $domain->fresh()->customerVisibleNotes());
        $this->actingAs($contact, 'portal')
            ->get(route('portal.domains'))
            ->assertOk()
            ->assertSee('Renewal invoice goes to finance.');
    }

    /**
     * Domain reminders are internal. The customer hears about renewals through
     * their contract, so a contact must never be on this mail.
     */
    public function test_expiry_reminders_go_to_staff_and_never_to_the_customer(): void
    {
        Notification::fake();

        $admin = User::factory()->admin()->create();
        $manager = User::factory()->manager()->create();
        $viewer = User::factory()->viewer()->create();
        $contact = $this->contact();

        $this->domain('klantnaam.nl', ['expires_at' => now()->addDays(6)->toDateString()]);

        $this->artisan(SendDomainExpiryReminders::class)->assertSuccessful();

        Notification::assertSentTo([$admin, $manager], DomainExpiryReminder::class);
        Notification::assertNotSentTo($viewer, DomainExpiryReminder::class);
        Notification::assertNotSentTo($contact, DomainExpiryReminder::class);
    }

    public function test_a_step_is_mailed_once_and_the_next_one_still_fires(): void
    {
        Notification::fake();
        User::factory()->admin()->create();

        $domain = $this->domain('klantnaam.nl', ['expires_at' => now()->addDays(10)->toDateString()]);

        $this->artisan(SendDomainExpiryReminders::class)->assertSuccessful();
        $this->assertSame([30, 14], $domain->fresh()->sent_offsets);

        // Same day again: nothing new to say.
        $this->artisan(SendDomainExpiryReminders::class)->assertSuccessful();
        Notification::assertSentTimes(DomainExpiryReminder::class, 1);

        // Closer in, the tighter step is still due.
        $domain->forceFill(['expires_at' => now()->addDays(5)->toDateString()])->save();
        $this->artisan(SendDomainExpiryReminders::class)->assertSuccessful();
        Notification::assertSentTimes(DomainExpiryReminder::class, 2);
    }

    public function test_the_dashboard_deck_lists_domains_that_need_attention(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $this->domain('binnenkort.nl', ['expires_at' => now()->addDays(10)->toDateString()]);
        $this->domain('rustig.nl', ['expires_at' => now()->addYear()->toDateString()]);

        $this->assertSame(1, UpcomingDomainExpiries::attentionCount());

        Livewire::test(AttentionBoard::class)
            ->assertSee(__('Domains'))
            ->assertDontSee(__('Nothing needs attention in the next 60 days.'));
    }

    public function test_the_admin_domain_list_and_detail_render(): void
    {
        $domain = $this->domain('klantnaam.nl');

        PlatformSettings::markOnboardingComplete();
        $this->actingAs(User::factory()->admin()->create());

        $this->get('/admin/domains')->assertOk();
        $this->get('/admin/domains/'.$domain->getKey())
            ->assertOk()
            ->assertSee('klantnaam.nl');
    }
}
