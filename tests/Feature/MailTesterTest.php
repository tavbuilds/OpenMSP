<?php

namespace Tests\Feature;

use App\Support\MailTester;
use App\Support\PlatformSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;
use Throwable;

/**
 * The settings page could configure SMTP but never tell you whether it
 * worked — which is how an install can sit on the log mailer for weeks.
 */
class MailTesterTest extends TestCase
{
    use RefreshDatabase;

    /** Testing the saved settings would be the wrong test. */
    public function test_it_runs_against_the_values_on_screen(): void
    {
        config(['mail.mailers.smtp.host' => 'saved.example']);

        $resolved = MailTester::resolveConfig([
            'mail_mailer' => 'smtp',
            'mail_host' => 'typed.example',
            'mail_port' => '587',
            'mail_username' => 'noreply@example.com',
            'mail_password' => 'typed-secret',
        ]);

        $this->assertSame('smtp', $resolved['mail.default']);
        $this->assertSame('typed.example', $resolved['mail.mailers.smtp.host']);
        $this->assertSame('587', $resolved['mail.mailers.smtp.port']);
        $this->assertSame('typed-secret', $resolved['mail.mailers.smtp.password']);
    }

    /** The password field is blank when unchanged; that is not "no password". */
    public function test_a_blank_password_falls_back_to_the_stored_one(): void
    {
        PlatformSettings::set(PlatformSettings::MAIL_PASSWORD, 'stored-secret');

        $resolved = MailTester::resolveConfig([
            'mail_mailer' => 'smtp',
            'mail_host' => 'typed.example',
            'mail_password' => '',
        ]);

        $this->assertSame('stored-secret', $resolved['mail.mailers.smtp.password']);
    }

    public function test_it_knows_when_nothing_actually_leaves_the_server(): void
    {
        config(['mail.default' => 'smtp']);

        $this->assertTrue(MailTester::writesToLogOnly(['mail_mailer' => 'log']));
        $this->assertTrue(MailTester::writesToLogOnly(['mail_mailer' => 'array']));
        $this->assertFalse(MailTester::writesToLogOnly(['mail_mailer' => 'smtp']));
        // No override on screen: whatever the app is already set to.
        $this->assertFalse(MailTester::writesToLogOnly([]));
        config(['mail.default' => 'log']);
        $this->assertTrue(MailTester::writesToLogOnly([]));
    }

    public function test_it_sends_and_puts_the_configuration_back(): void
    {
        config(['mail.default' => 'smtp', 'mail.mailers.smtp.host' => 'real.example']);
        // The array mailer keeps the message in memory, so this exercises the
        // real send path without anything leaving the machine.
        Event::fake([MessageSent::class]);

        MailTester::send('admin@example.com', [
            'mail_mailer' => 'array',
            'mail_host' => 'test.example',
        ]);

        Event::assertDispatched(MessageSent::class, function (MessageSent $event): bool {
            $to = $event->message->getTo();

            return count($to) === 1 && $to[0]->getAddress() === 'admin@example.com';
        });
        // An unsaved test must not leave its settings behind.
        $this->assertSame('smtp', config('mail.default'));
        $this->assertSame('real.example', config('mail.mailers.smtp.host'));
    }

    /** A failure has to surface — and still not poison the configuration. */
    public function test_a_failure_is_thrown_and_the_configuration_is_still_restored(): void
    {
        config(['mail.default' => 'smtp', 'mail.mailers.smtp.host' => 'real.example']);

        try {
            MailTester::send('admin@example.com', ['mail_mailer' => 'nonexistent-mailer']);
            $this->fail('Expected the mailer to refuse.');
        } catch (Throwable) {
            // expected
        }

        $this->assertSame('smtp', config('mail.default'));
        $this->assertSame('real.example', config('mail.mailers.smtp.host'));
    }
}
