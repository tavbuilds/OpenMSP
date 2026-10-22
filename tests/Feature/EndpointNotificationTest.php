<?php

namespace Tests\Feature;

use App\Enums\EndpointKind;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Endpoint;
use App\Models\User;
use App\Notifications\ReminderDigest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class EndpointNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_due_notification_picks_tightest_enabled_offset(): void
    {
        $endpoint = Endpoint::create([
            'name' => 'Soon',
            'kind' => EndpointKind::Certificate,
            'expires_at' => now()->addDays(12)->toDateString(),
        ]);
        $this->assertSame(14, $endpoint->dueNotification());

        $endpoint->forceFill(['notify_14' => false])->save();
        $this->assertSame(30, $endpoint->fresh()->dueNotification());

        $endpoint->forceFill(['notify_14' => true, 'notify_30' => true])->save();
        $endpoint->fresh()->markNotified(14);
        $this->assertNull($endpoint->fresh()->dueNotification());
    }

    public function test_expired_toggle_controls_expiry_mail(): void
    {
        Notification::fake();
        $admin = User::factory()->admin()->create();

        $on = Endpoint::create([
            'name' => 'Expired on',
            'kind' => EndpointKind::Certificate,
            'expires_at' => now()->subDay()->toDateString(),
            'notify_expired' => true,
        ]);
        $off = Endpoint::create([
            'name' => 'Expired off',
            'kind' => EndpointKind::Certificate,
            'expires_at' => now()->subDay()->toDateString(),
            'notify_expired' => false,
        ]);

        $this->artisan('endpoints:send-expiry-reminders')->assertSuccessful();

        Notification::assertSentTo($admin, ReminderDigest::class, function (ReminderDigest $d) use ($on) {
            return $d->records->contains(fn ($e) => $e->is($on))
                && $d->context[$on->getKey()]['days'] === null;
        });
        Notification::assertSentTo($admin, ReminderDigest::class, function (ReminderDigest $d) use ($off) {
            return ! $d->records->contains(fn ($e) => $e->is($off));
        });
        $this->assertNotNull($on->fresh()->expired_notified_at);

        Notification::fake();
        $this->artisan('endpoints:send-expiry-reminders')->assertSuccessful();
        Notification::assertNothingSent();
    }

    public function test_customer_mail_only_when_toggle_on(): void
    {
        Notification::fake();
        User::factory()->admin()->create();
        $company = Company::create(['name' => 'Klant', 'country' => 'NL']);
        $contact = Contact::create([
            'company_id' => $company->id,
            'name' => 'Piet',
            'email' => 'piet@example.com',
        ]);

        Endpoint::create([
            'company_id' => $company->id,
            'name' => 'With customer',
            'kind' => EndpointKind::Certificate,
            'expires_at' => now()->addDays(7)->toDateString(),
            'notify_customer' => true,
        ]);
        Endpoint::create([
            'company_id' => $company->id,
            'name' => 'Staff only',
            'kind' => EndpointKind::Certificate,
            'expires_at' => now()->addDays(7)->toDateString(),
            'notify_customer' => false,
        ]);

        $this->artisan('endpoints:send-expiry-reminders')->assertSuccessful();

        // The customer's digest holds theirs and only theirs.
        Notification::assertSentToTimes($contact, ReminderDigest::class, 1);
        Notification::assertSentTo($contact, ReminderDigest::class, function (ReminderDigest $d) {
            return $d->forCustomer === true
                && $d->records->pluck('name')->all() === ['With customer'];
        });
    }
}
