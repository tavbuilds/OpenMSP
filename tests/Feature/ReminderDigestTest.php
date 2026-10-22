<?php

namespace Tests\Feature;

use App\Console\Commands\SendDomainExpiryReminders;
use App\Models\Company;
use App\Models\Domain;
use App\Models\User;
use App\Notifications\ReminderDigest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * One mail per kind, in the language the reader picked.
 *
 * Both halves of that matter. Six separate mails is how an inbox teaches
 * someone to ignore all six; an English mail to someone who set the
 * panel to Dutch is a mail they have to translate before they can act.
 */
class ReminderDigestTest extends TestCase
{
    use RefreshDatabase;

    private function domain(string $name, int $daysOut): Domain
    {
        return Domain::create([
            'name' => $name,
            'expires_at' => now()->addDays($daysOut)->toDateString(),
            'auto_renew' => false,
        ]);
    }

    /** Renders the digest the way Laravel does: in the reader's locale. */
    private function asReadBy(User $reader, ReminderDigest $digest): string
    {
        $was = app()->getLocale();
        app()->setLocale($reader->preferredLocale() ?? $was);

        try {
            return $digest->toMail($reader)->subject;
        } finally {
            app()->setLocale($was);
        }
    }

    public function test_the_digest_is_written_in_each_readers_own_language(): void
    {
        Notification::fake();

        $dutch = User::factory()->admin()->create(['name' => 'Tav', 'language' => 'nl']);
        $german = User::factory()->manager()->create(['name' => 'Jonas', 'language' => 'de']);

        $this->domain('klantnaam.nl', 6);
        $this->artisan(SendDomainExpiryReminders::class)->assertSuccessful();

        $subjects = [];
        foreach ([$dutch, $german] as $reader) {
            Notification::assertSentTo($reader, ReminderDigest::class, function (ReminderDigest $digest) use ($reader, &$subjects) {
                // Laravel sets the locale around toMail() from the
                // notifiable, which is what makes one digest readable by
                // people who do not share a language.
                $subjects[$reader->language] = $this->asReadBy($reader, $digest);

                return true;
            });
        }

        $this->assertStringContainsString('domein', $subjects['nl']);
        $this->assertStringContainsString('Domain', $subjects['de']);
        $this->assertNotSame($subjects['nl'], $subjects['de']);
    }

    /** Nothing stored, so whatever the platform is set to. */
    public function test_a_reader_who_never_chose_gets_the_platform_language(): void
    {
        $user = User::factory()->admin()->create(['language' => null]);

        $this->assertNull($user->preferredLocale());
    }

    public function test_a_stored_language_we_do_not_have_falls_back(): void
    {
        $user = User::factory()->admin()->create(['language' => 'xx']);

        $this->assertNull($user->preferredLocale());
    }

    /** Choosing a language in the panel has to outlive the browser. */
    public function test_switching_language_stores_it_on_the_account(): void
    {
        $user = User::factory()->admin()->create(['language' => null]);

        $this->actingAs($user)->get('/locale/nl')->assertRedirect();

        $this->assertSame('nl', $user->fresh()->language);
    }

    public function test_the_digest_lists_every_due_item_with_its_own_urgency(): void
    {
        Notification::fake();
        $admin = User::factory()->admin()->create(['language' => 'en']);
        $company = Company::create(['name' => 'Klant BV', 'country' => 'NL']);

        $soon = $this->domain('soon.nl', 6);
        $soon->forceFill(['company_id' => $company->id])->save();
        $later = $this->domain('later.nl', 29);

        $this->artisan(SendDomainExpiryReminders::class)->assertSuccessful();

        Notification::assertSentTo($admin, ReminderDigest::class, function (ReminderDigest $digest) use ($admin, $soon, $later) {
            $body = (string) $digest->toMail($admin)->render();

            return str_contains($body, 'soon.nl')
                && str_contains($body, 'later.nl')
                && str_contains($body, 'Klant BV')
                && $digest->context[$soon->getKey()]['days'] === 7
                && $digest->context[$later->getKey()]['days'] === 30;
        });
    }

    /** Already past sorts above merely soon: it is the worse problem. */
    public function test_what_has_already_expired_comes_first(): void
    {
        Notification::fake();
        $admin = User::factory()->admin()->create(['language' => 'en']);

        $this->domain('soon.nl', 6);
        $this->domain('gone.nl', -3);

        $this->artisan(SendDomainExpiryReminders::class)->assertSuccessful();

        Notification::assertSentTo($admin, ReminderDigest::class, function (ReminderDigest $digest) use ($admin) {
            $body = (string) $digest->toMail($admin)->render();

            return strpos($body, 'gone.nl') < strpos($body, 'soon.nl');
        });
    }
}
