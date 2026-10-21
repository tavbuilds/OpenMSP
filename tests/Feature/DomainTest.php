<?php

namespace Tests\Feature;

use App\Console\Commands\SendDomainExpiryReminders;
use App\Filament\Resources\Domains\Pages\ListDomains;
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

    /**
     * A domain the registrar let go is history, not a job: it drops out of
     * the list, the watchlist, the customer's tab and the reminder run —
     * without losing its customer, its notes or its dates.
     */
    public function test_a_removed_domain_leaves_the_default_list_but_is_still_findable(): void
    {
        $this->domain('nogsteeds.nl');
        $this->domain('weggegooid.nl', ['removed_at' => now()]);

        PlatformSettings::markOnboardingComplete();
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(ListDomains::class)
            // The filter opens on "Current", so the list says what it is
            // showing rather than silently leaving rows out.
            ->assertSet('tableFilters.lifecycle.value', 'present')
            ->assertCanSeeTableRecords(Domain::query()->where('name', 'nogsteeds.nl')->get())
            ->assertCanNotSeeTableRecords(Domain::query()->where('name', 'weggegooid.nl')->get())
            ->filterTable('lifecycle', 'removed')
            ->assertCanSeeTableRecords(Domain::query()->where('name', 'weggegooid.nl')->get())
            ->assertCanNotSeeTableRecords(Domain::query()->where('name', 'nogsteeds.nl')->get())
            ->filterTable('lifecycle', 'all')
            ->assertCanSeeTableRecords(Domain::query()->get());
    }

    public function test_a_removed_domain_gets_no_more_reminders(): void
    {
        Notification::fake();
        User::factory()->admin()->create();

        $this->domain('weggegooid.nl', [
            'expires_at' => now()->subDays(50)->toDateString(),
            'removed_at' => now(),
        ]);

        $this->artisan(SendDomainExpiryReminders::class)->assertSuccessful();

        Notification::assertNothingSent();
    }

    public function test_a_removed_domain_is_off_the_watchlist_and_out_of_the_portal(): void
    {
        $this->domain('weggegooid.nl', [
            'expires_at' => now()->addDays(10)->toDateString(),
            'removed_at' => now(),
        ]);

        $this->assertSame(0, UpcomingDomainExpiries::attentionCount());

        $this->actingAs($this->contact(), 'portal')
            ->get(route('portal.domains'))
            ->assertOk()
            ->assertDontSee('weggegooid.nl');
    }

    /** Red is for something to do; a domain we no longer hold is not that. */
    public function test_a_removed_domain_is_not_shown_as_an_emergency(): void
    {
        $domain = $this->domain('weggegooid.nl', [
            'expires_at' => now()->subDays(50)->toDateString(),
            'removed_at' => now(),
        ]);

        $this->assertSame('removed', $domain->expiryStatus());
        $this->assertSame('gray', $domain->statusColor());
        $this->assertSame(__('No longer at the registrar'), $domain->statusLabel());
    }

    public function test_the_detail_page_says_why_a_domain_is_gone(): void
    {
        $domain = $this->domain('weggegooid.nl', ['removed_at' => now()]);

        PlatformSettings::markOnboardingComplete();
        $this->actingAs(User::factory()->admin()->create());

        $this->get('/admin/domains/'.$domain->getKey())
            ->assertOk()
            ->assertSee(__('No longer at the registrar since'));
    }

    /**
     * Every domain wore the same blue badge, so a live one and one in
     * quarantine read identically in a list you are scanning.
     */
    public function test_the_registrar_status_is_coloured_by_what_it_means(): void
    {
        $cases = [
            'ACT' => 'success',
            'PEN' => 'warning',
            'REQ' => 'warning',
            'PRE' => 'warning',
            'SCH' => 'warning',
            'RGP' => 'danger',
            'DEL' => 'danger',
            'FAI' => 'danger',
            'REJ' => 'danger',
        ];

        foreach ($cases as $code => $colour) {
            $domain = $this->domain(strtolower($code).'.nl', ['status' => $code]);

            $this->assertSame($colour, $domain->registrarStatusColor(), "status {$code}");
        }
    }

    /** A code we have never seen is not worth guessing a colour for. */
    public function test_an_unknown_registrar_status_stays_grey(): void
    {
        $domain = $this->domain('onbekend.nl', ['status' => 'XYZ']);

        $this->assertSame('gray', $domain->registrarStatusColor());
        // ...and still shows the registrar's own word for it.
        $this->assertSame('XYZ', $domain->registrarStatusLabel());
    }

    /** A domain we no longer hold is history, whatever it last said. */
    public function test_a_removed_domain_is_grey_whatever_its_last_status(): void
    {
        $domain = $this->domain('weg.nl', ['status' => 'ACT', 'removed_at' => now()]);

        $this->assertSame('gray', $domain->registrarStatusColor());
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
