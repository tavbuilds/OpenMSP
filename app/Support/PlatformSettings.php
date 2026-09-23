<?php

namespace App\Support;

use App\Models\Setting;
use Filament\Support\Colors\Color;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Key-value platform settings stored in the database.
 * Secrets are encrypted with APP_KEY. Empty / missing keys fall back to env.
 */
class PlatformSettings
{
    public const NAME = 'platform.name';

    public const MAIL_MAILER = 'mail.mailer';

    public const MAIL_HOST = 'mail.host';

    public const MAIL_PORT = 'mail.port';

    public const MAIL_USERNAME = 'mail.username';

    public const MAIL_PASSWORD = 'mail.password';

    public const MAIL_SCHEME = 'mail.scheme';

    public const MAIL_FROM_ADDRESS = 'mail.from_address';

    public const MAIL_FROM_NAME = 'mail.from_name';

    public const STRIPE_KEY = 'stripe.key';

    public const STRIPE_SECRET = 'stripe.secret';

    public const STRIPE_WEBHOOK_SECRET = 'stripe.webhook_secret';

    public const PAX8_CLIENT_ID = 'pax8.client_id';

    public const PAX8_CLIENT_SECRET = 'pax8.client_secret';

    public const PAX8_AUDIENCE = 'pax8.audience';

    public const PAX8_LAST_SYNC_AT = 'pax8.last_sync_at';

    public const PAX8_LAST_SYNC_REPORT = 'pax8.last_sync_report';

    public const PAX8_LAST_ERROR = 'pax8.last_error';

    public const OPENPROVIDER_USERNAME = 'openprovider.username';

    public const OPENPROVIDER_PASSWORD = 'openprovider.password';

    /** Host and version are settings so a future API version is a config change. */
    public const OPENPROVIDER_HOST = 'openprovider.host';

    public const OPENPROVIDER_VERSION = 'openprovider.version';

    public const OPENPROVIDER_LAST_SYNC_AT = 'openprovider.last_sync_at';

    public const OPENPROVIDER_LAST_SYNC_REPORT = 'openprovider.last_sync_report';

    public const OPENPROVIDER_LAST_ERROR = 'openprovider.last_error';

    public const REQUIRE_MFA = 'security.require_mfa';

    public const ONBOARDING_COMPLETED = 'onboarding.completed';

    public const LOGO = 'branding.logo';

    public const FAVICON = 'branding.favicon';

    public const PRIMARY_COLOR = 'branding.primary_color';

    public const DEFAULT_PRIMARY_COLOR = '#2563eb';

    /** @var list<string> */
    public const ENCRYPTED_KEYS = [
        self::MAIL_PASSWORD,
        self::STRIPE_SECRET,
        self::STRIPE_WEBHOOK_SECRET,
        self::PAX8_CLIENT_SECRET,
        self::OPENPROVIDER_PASSWORD,
    ];

    public static function ready(): bool
    {
        try {
            return Schema::hasTable('settings');
        } catch (Throwable) {
            return false;
        }
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        if (! self::ready()) {
            return $default;
        }

        $row = Setting::query()->find($key);
        if ($row === null || $row->value === null || $row->value === '') {
            return $default;
        }

        if ($row->encrypted) {
            try {
                return Crypt::decryptString($row->value);
            } catch (Throwable) {
                return $default;
            }
        }

        return $row->value;
    }

    public static function set(string $key, mixed $value, bool $encrypt = false): void
    {
        if (! self::ready()) {
            return;
        }

        $encrypt = $encrypt || in_array($key, self::ENCRYPTED_KEYS, true);
        $stored = $value;

        if ($value === null || $value === '') {
            Setting::query()->where('key', $key)->delete();

            return;
        }

        if ($encrypt && is_string($value)) {
            $stored = Crypt::encryptString($value);
        } elseif (is_bool($value)) {
            $stored = $value ? '1' : '0';
            $encrypt = false;
        } elseif (! is_string($value)) {
            $stored = (string) $value;
        }

        Setting::query()->updateOrCreate(
            ['key' => $key],
            ['value' => $stored, 'encrypted' => $encrypt],
        );
    }

    public static function forget(string $key): void
    {
        if (! self::ready()) {
            return;
        }

        Setting::query()->where('key', $key)->delete();
    }

    public static function name(): string
    {
        $fromDb = self::get(self::NAME);
        if (filled($fromDb)) {
            return (string) $fromDb;
        }

        $env = config('app.name');

        return filled($env) && $env !== 'Laravel' ? (string) $env : 'MSP Platform';
    }

    public static function onboardingCompleted(): bool
    {
        return self::get(self::ONBOARDING_COMPLETED) === '1';
    }

    public static function markOnboardingComplete(): void
    {
        self::set(self::ONBOARDING_COMPLETED, '1');
    }

    public static function resetOnboarding(): void
    {
        self::forget(self::ONBOARDING_COMPLETED);
    }

    public static function requireMfa(): bool
    {
        return self::get(self::REQUIRE_MFA, '0') === '1';
    }

    public static function primaryColor(): string
    {
        $value = self::get(self::PRIMARY_COLOR, self::DEFAULT_PRIMARY_COLOR);
        if (is_string($value) && preg_match('/^#?[0-9A-Fa-f]{6}$/', $value)) {
            return str_starts_with($value, '#') ? $value : '#'.$value;
        }

        return self::DEFAULT_PRIMARY_COLOR;
    }

    /** @return array<int|string, string> */
    public static function filamentPrimary(): array
    {
        try {
            return Color::hex(self::primaryColor());
        } catch (Throwable) {
            return Color::Blue;
        }
    }

    public static function logoPath(): ?string
    {
        $path = self::get(self::LOGO);

        return filled($path) ? (string) $path : null;
    }

    public static function faviconPath(): ?string
    {
        $path = self::get(self::FAVICON);

        return filled($path) ? (string) $path : null;
    }

    public static function logoUrl(): ?string
    {
        return self::publicUrl(self::logoPath());
    }

    public static function faviconUrl(): ?string
    {
        return self::publicUrl(self::faviconPath());
    }

    public static function publicUrl(?string $path): ?string
    {
        if (! filled($path)) {
            return null;
        }

        return Storage::disk('public')->url($path);
    }

    /**
     * Overlay DB settings onto runtime config (env remains the fallback).
     */
    public static function applyToConfig(): void
    {
        if (! self::ready()) {
            return;
        }

        $name = self::get(self::NAME);
        if (filled($name)) {
            config(['app.name' => $name]);
        }

        $fromAddress = self::get(self::MAIL_FROM_ADDRESS);
        if (filled($fromAddress)) {
            config(['mail.from.address' => $fromAddress]);
        }

        $fromName = self::get(self::MAIL_FROM_NAME);
        if (filled($fromName)) {
            config(['mail.from.name' => $fromName]);
        } elseif (filled($name)) {
            config(['mail.from.name' => $name]);
        }

        $mailer = self::get(self::MAIL_MAILER);
        if (filled($mailer)) {
            config(['mail.default' => $mailer]);
        }

        foreach ([
            self::MAIL_HOST => 'mail.mailers.smtp.host',
            self::MAIL_PORT => 'mail.mailers.smtp.port',
            self::MAIL_USERNAME => 'mail.mailers.smtp.username',
            self::MAIL_PASSWORD => 'mail.mailers.smtp.password',
            self::MAIL_SCHEME => 'mail.mailers.smtp.scheme',
        ] as $key => $configKey) {
            $value = self::get($key);
            if (filled($value)) {
                config([$configKey => $value]);
            }
        }

        foreach ([
            self::STRIPE_KEY => 'services.stripe.key',
            self::STRIPE_SECRET => 'services.stripe.secret',
            self::STRIPE_WEBHOOK_SECRET => 'services.stripe.webhook_secret',
        ] as $key => $configKey) {
            $value = self::get($key);
            if (filled($value)) {
                config([$configKey => $value]);
            }
        }
    }
}
