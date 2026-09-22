<?php

namespace Tests\Feature;

use App\Support\LocaleCatalog;
use Tests\TestCase;

/**
 * The panel and the portal ship twelve languages. A string that reaches a
 * screen without a translation silently falls back to English, which is easy
 * to miss in review — these tests fail instead.
 */
class TranslationCatalogueTest extends TestCase
{
    /**
     * Identifiers, acronyms and example values that read the same in every
     * language and are deliberately left out of the catalogue.
     *
     * @var list<string>
     */
    private const UNTRANSLATED = [
        'ARR',
        'IP',
        'MAIL_SCHEME',
        'MRR',
        'URL',
        '—',
        'e.g. VPN certificate or mail.customer.com',
        'https://vpn.customer.nl',
        'vpn.customer.nl',
    ];

    public function test_every_locale_covers_every_translatable_string(): void
    {
        $used = $this->translatableStrings();
        $this->assertNotEmpty($used, 'No __() calls found — the scanner is broken.');

        foreach (LocaleCatalog::CODES as $code) {
            if ($code === LocaleCatalog::DEFAULT) {
                continue; // English is the source language.
            }

            $catalogue = $this->catalogue($code);
            $missing = array_values(array_diff($used, self::UNTRANSLATED, array_keys($catalogue)));
            sort($missing);

            $this->assertSame([], $missing, sprintf(
                'lang/%s.json is missing %d string(s), e.g. %s',
                $code,
                count($missing),
                json_encode(array_slice($missing, 0, 3), JSON_UNESCAPED_UNICODE),
            ));
        }
    }

    public function test_translations_keep_their_placeholders(): void
    {
        foreach (LocaleCatalog::CODES as $code) {
            if ($code === LocaleCatalog::DEFAULT) {
                continue;
            }

            foreach ($this->catalogue($code) as $key => $value) {
                preg_match_all('/:[a-z][a-zA-Z_]*/', $key, $matches);

                foreach (array_unique($matches[0]) as $placeholder) {
                    $this->assertStringContainsString($placeholder, $value, sprintf(
                        'lang/%s.json drops %s from "%s"',
                        $code,
                        $placeholder,
                        $key,
                    ));
                }
            }
        }
    }

    /** @return array<string, string> */
    private function catalogue(string $code): array
    {
        $path = base_path("lang/{$code}.json");
        $this->assertFileExists($path);

        return json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
    }

    /**
     * Every literal passed to __() in application code and views.
     *
     * @return list<string>
     */
    private function translatableStrings(): array
    {
        $found = [];

        foreach ([app_path(), resource_path('views')] as $directory) {
            $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory));

            foreach ($files as $file) {
                if ($file->getExtension() !== 'php') {
                    continue;
                }

                $contents = (string) file_get_contents($file->getPathname());

                // __('...') and __("...") with no interpolation.
                preg_match_all('/__\(\s*\'((?:[^\'\\\\]|\\\\.)*)\'/', $contents, $single);
                preg_match_all('/__\(\s*"((?:[^"\\\\$]|\\\\.)*)"/', $contents, $double);

                foreach ($single[1] as $match) {
                    $found[] = str_replace("\\'", "'", $match);
                }

                foreach ($double[1] as $match) {
                    $found[] = str_replace('\\"', '"', $match);
                }
            }
        }

        return array_values(array_unique($found));
    }
}
