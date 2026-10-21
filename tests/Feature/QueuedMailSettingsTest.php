<?php

namespace Tests\Feature;

use App\Filament\Pages\ManageSettings;
use App\Models\User;
use App\Support\PlatformSettings;
use App\Support\QueueHealth;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Mockery;
use Tests\TestCase;

/**
 * A queue worker boots once and runs for hours. Every notification in this
 * application is queued, so a worker holding the mail settings it started
 * with sends all of them to the wrong place — while the test button, which
 * runs inside an ordinary request, reports success.
 */
class QueuedMailSettingsTest extends TestCase
{
    use RefreshDatabase;

    private function jobStarts(): void
    {
        $job = Mockery::mock(Job::class)->shouldIgnoreMissing([]);

        event(new JobProcessing('database', $job));
    }

    public function test_a_job_picks_up_mail_settings_saved_after_the_worker_booted(): void
    {
        // What this process booted with.
        config(['mail.default' => 'log', 'mail.mailers.smtp.host' => 'old.example']);

        // What the operator typed into Settings an hour later.
        PlatformSettings::set(PlatformSettings::MAIL_MAILER, 'smtp');
        PlatformSettings::set(PlatformSettings::MAIL_HOST, 'new.example');

        // Nothing has told this process about it yet.
        $this->assertSame('log', config('mail.default'));

        $this->jobStarts();

        $this->assertSame('smtp', config('mail.default'));
        $this->assertSame('new.example', config('mail.mailers.smtp.host'));
    }

    /**
     * Fresh config is not enough: a mailer resolved earlier holds a transport
     * built from the old host and would keep using it.
     */
    public function test_the_resolved_mailer_is_rebuilt_too(): void
    {
        config(['mail.default' => 'smtp', 'mail.mailers.smtp.host' => 'old.example']);
        $this->assertStringContainsString('old.example', (string) Mail::mailer('smtp')->getSymfonyTransport());

        PlatformSettings::set(PlatformSettings::MAIL_MAILER, 'smtp');
        PlatformSettings::set(PlatformSettings::MAIL_HOST, 'new.example');

        $this->jobStarts();

        $this->assertStringContainsString('new.example', (string) Mail::mailer('smtp')->getSymfonyTransport());
    }

    /** Stripe keys live in the same place and go stale the same way. */
    public function test_stripe_credentials_are_refreshed_as_well(): void
    {
        config(['services.stripe.secret' => 'sk_old']);
        PlatformSettings::set(PlatformSettings::STRIPE_SECRET, 'sk_new');

        $this->jobStarts();

        $this->assertSame('sk_new', config('services.stripe.secret'));
    }

    public function test_a_stalled_queue_is_reported_on_the_settings_page(): void
    {
        // Tests run on the sync driver, where there is no queue to stall.
        config(['queue.default' => 'database']);
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(ManageSettings::class)->assertDontSee(__('Nothing is working the queue'), false);

        // A job nobody has touched for an hour: there is no worker.
        DB::table('jobs')->insert([
            'queue' => 'default',
            'payload' => '{}',
            'attempts' => 0,
            'reserved_at' => null,
            'available_at' => now()->subHour()->timestamp,
            'created_at' => now()->subHour()->timestamp,
        ]);

        $this->assertTrue(QueueHealth::looksStalled());
        $this->assertSame(1, QueueHealth::pending());

        Livewire::test(ManageSettings::class)->assertSee(__('Nothing is working the queue'), false);
    }

    /** A job queued a moment ago is a working queue, not a stalled one. */
    public function test_a_job_that_was_just_queued_is_not_called_stalled(): void
    {
        config(['queue.default' => 'database']);

        DB::table('jobs')->insert([
            'queue' => 'default',
            'payload' => '{}',
            'attempts' => 0,
            'reserved_at' => null,
            'available_at' => now()->timestamp,
            'created_at' => now()->timestamp,
        ]);

        $this->assertFalse(QueueHealth::looksStalled());
    }
}
