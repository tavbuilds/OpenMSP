<?php

namespace App\Support;

use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Sends one message with the mail settings exactly as they stand on screen.
 *
 * Testing the saved settings would be the wrong test: you want to know
 * whether what you just typed works, before committing it. So the overrides
 * go into config for the length of this one send and are put back after.
 *
 * The send is deliberately synchronous. A queued test proves nothing about
 * the mail server — only that the job was accepted.
 */
final class MailTester
{
    /** Settings-page field => config key. */
    private const OVERRIDES = [
        'mail_mailer' => 'mail.default',
        'mail_from_address' => 'mail.from.address',
        'mail_from_name' => 'mail.from.name',
        'mail_host' => 'mail.mailers.smtp.host',
        'mail_port' => 'mail.mailers.smtp.port',
        'mail_username' => 'mail.mailers.smtp.username',
        'mail_password' => 'mail.mailers.smtp.password',
        'mail_scheme' => 'mail.mailers.smtp.scheme',
    ];

    /**
     * @param  array<string, mixed>  $settings  Settings-page form state.
     *
     * @throws Throwable whatever the mailer threw, unchanged
     */
    public static function send(string $to, array $settings = []): void
    {
        $restore = [];
        foreach (self::OVERRIDES as $configKey) {
            $restore[$configKey] = config($configKey);
        }

        try {
            self::apply($settings);

            Mail::raw(self::body(), function ($message) use ($to): void {
                $message->to($to)->subject(__(':platform: test email', [
                    'platform' => PlatformSettings::name(),
                ]));
            });
        } finally {
            config($restore);
            // The manager caches a built mailer per name; without this the
            // next send in this request would reuse the test's transport.
            Mail::forgetMailers();
        }
    }

    /**
     * Whether the send fell back to the stored password because the field was
     * left blank. A 535 means nothing until you know which password was tried.
     *
     * @param  array<string, mixed>  $settings
     */
    public static function usedStoredPassword(array $settings = []): bool
    {
        return blank($settings['mail_password'] ?? null)
            && filled(PlatformSettings::get(PlatformSettings::MAIL_PASSWORD));
    }

    /** Whether this send only writes to the log, rather than leaving the server. */
    public static function writesToLogOnly(array $settings = []): bool
    {
        $mailer = $settings['mail_mailer'] ?? null;
        $mailer = filled($mailer) ? $mailer : config('mail.default');

        return in_array($mailer, ['log', 'array'], true);
    }

    /**
     * The config this send will run under: the screen's values on top of
     * whatever is already configured.
     *
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>
     */
    public static function resolveConfig(array $settings): array
    {
        $resolved = [];

        foreach (self::OVERRIDES as $field => $configKey) {
            $value = $settings[$field] ?? null;
            if ($field === 'mail_scheme') {
                $value = PlatformSettings::normaliseMailScheme($value);
            }
            if (filled($value)) {
                $resolved[$configKey] = $value;
            }
        }

        // The password field is left blank when it is unchanged, so an empty
        // one means "the stored password", not "no password".
        if (blank($settings['mail_password'] ?? null)) {
            $stored = PlatformSettings::get(PlatformSettings::MAIL_PASSWORD);
            if (filled($stored)) {
                $resolved['mail.mailers.smtp.password'] = $stored;
            }
        }

        return $resolved;
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    private static function apply(array $settings): void
    {
        config(self::resolveConfig($settings));
        Mail::forgetMailers();
    }

    private static function body(): string
    {
        return implode("\n", [
            __('This is a test message from :platform.', ['platform' => PlatformSettings::name()]),
            '',
            __('If you are reading it, outgoing mail works: renewal reminders, expiry warnings and portal magic links will arrive.'),
            '',
            __('Sent at :time.', ['time' => now()->toDayDateTimeString()]),
        ]);
    }
}
