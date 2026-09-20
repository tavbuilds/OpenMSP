<?php

namespace App\Support;

/**
 * UI languages. Stored choice wins, then Accept-Language, then APP_LOCALE, then English.
 */
final class LocaleCatalog
{
    public const COOKIE = 'msp.locale';

    public const DEFAULT = 'en';

    /** @var list<string> */
    public const CODES = ['nl', 'en', 'de', 'fr', 'es', 'it', 'pt', 'da', 'sv', 'fi', 'pl', 'el', 'tr'];

    /**
     * @return array<string, array{native: string, english: string, html: string, intl: string}>
     */
    public static function all(): array
    {
        return [
            'nl' => ['native' => 'Nederlands', 'english' => 'Dutch', 'html' => 'nl', 'intl' => 'nl-NL'],
            'en' => ['native' => 'English', 'english' => 'English', 'html' => 'en', 'intl' => 'en-GB'],
            'de' => ['native' => 'Deutsch', 'english' => 'German', 'html' => 'de', 'intl' => 'de-DE'],
            'fr' => ['native' => 'Français', 'english' => 'French', 'html' => 'fr', 'intl' => 'fr-FR'],
            'es' => ['native' => 'Español', 'english' => 'Spanish', 'html' => 'es', 'intl' => 'es-ES'],
            'it' => ['native' => 'Italiano', 'english' => 'Italian', 'html' => 'it', 'intl' => 'it-IT'],
            'pt' => ['native' => 'Português', 'english' => 'Portuguese', 'html' => 'pt', 'intl' => 'pt-PT'],
            'da' => ['native' => 'Dansk', 'english' => 'Danish', 'html' => 'da', 'intl' => 'da-DK'],
            'sv' => ['native' => 'Svenska', 'english' => 'Swedish', 'html' => 'sv', 'intl' => 'sv-SE'],
            'fi' => ['native' => 'Suomi', 'english' => 'Finnish', 'html' => 'fi', 'intl' => 'fi-FI'],
            'pl' => ['native' => 'Polski', 'english' => 'Polish', 'html' => 'pl', 'intl' => 'pl-PL'],
            'el' => ['native' => 'Ελληνικά', 'english' => 'Greek', 'html' => 'el', 'intl' => 'el-GR'],
            'tr' => ['native' => 'Türkçe', 'english' => 'Turkish', 'html' => 'tr', 'intl' => 'tr-TR'],
        ];
    }

    public static function isSupported(?string $code): bool
    {
        return self::match($code) !== null;
    }

    public static function match(?string $tag): ?string
    {
        if ($tag === null) {
            return null;
        }
        $tag = str_replace('_', '-', strtolower(trim($tag)));
        if ($tag === '') {
            return null;
        }
        foreach (self::CODES as $code) {
            if ($tag === $code) {
                return $code;
            }
        }
        $lang = explode('-', $tag)[0];
        foreach (self::CODES as $code) {
            if ($lang === $code) {
                return $code;
            }
        }

        return null;
    }

    /**
     * @param  list<string>|null  $navigatorLanguages
     */
    public static function detect(
        ?string $stored = null,
        ?string $acceptLanguage = null,
        ?array $navigatorLanguages = null,
        ?string $platformDefault = null,
    ): string {
        if ($matched = self::match($stored)) {
            return $matched;
        }
        foreach (self::parseAcceptLanguage($acceptLanguage) as $tag) {
            if ($matched = self::match($tag)) {
                return $matched;
            }
        }
        foreach ($navigatorLanguages ?? [] as $tag) {
            if ($matched = self::match($tag)) {
                return $matched;
            }
        }
        if ($matched = self::match($platformDefault)) {
            return $matched;
        }

        return self::DEFAULT;
    }

    /**
     * @return list<string>
     */
    public static function parseAcceptLanguage(?string $header): array
    {
        if ($header === null || trim($header) === '') {
            return [];
        }
        $parts = [];
        foreach (explode(',', $header) as $chunk) {
            $chunk = trim($chunk);
            if ($chunk === '') {
                continue;
            }
            $bit = explode(';', $chunk)[0];
            $parts[] = trim($bit);
        }

        return $parts;
    }

    public static function intl(string $code): string
    {
        return self::all()[$code]['intl'] ?? 'en-GB';
    }
}
