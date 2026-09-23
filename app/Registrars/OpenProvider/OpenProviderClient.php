<?php

namespace App\Registrars\OpenProvider;

use App\Support\PlatformSettings;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Openprovider's reseller API.
 *
 * Everything version-specific lives here. Their published spec is still
 * /v1beta while a successor is in the works, so both the host and the version
 * segment are settings — a move is a settings change, not a rewrite.
 *
 * @see https://github.com/openprovider/api-documentation
 */
class OpenProviderClient
{
    public const SOURCE = 'openprovider';

    public const DEFAULT_HOST = 'https://api.openprovider.eu';

    public const DEFAULT_VERSION = 'v1beta';

    /** Openprovider caps a page at 500 rows. */
    private const PAGE_SIZE = 200;

    /** How many extensions to put in one /tlds call. */
    private const EXTENSIONS_PER_REQUEST = 50;

    public function configured(): bool
    {
        return filled(PlatformSettings::get(PlatformSettings::OPENPROVIDER_USERNAME))
            && filled(PlatformSettings::get(PlatformSettings::OPENPROVIDER_PASSWORD));
    }

    public function host(): string
    {
        $host = (string) PlatformSettings::get(PlatformSettings::OPENPROVIDER_HOST, self::DEFAULT_HOST);

        return rtrim($host !== '' ? $host : self::DEFAULT_HOST, '/');
    }

    public function version(): string
    {
        $version = (string) PlatformSettings::get(PlatformSettings::OPENPROVIDER_VERSION, self::DEFAULT_VERSION);

        return trim($version !== '' ? $version : self::DEFAULT_VERSION, '/');
    }

    public function token(): string
    {
        $cacheKey = 'openprovider.access_token';
        $cached = Cache::get($cacheKey);
        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $username = (string) PlatformSettings::get(PlatformSettings::OPENPROVIDER_USERNAME);
        $password = (string) PlatformSettings::get(PlatformSettings::OPENPROVIDER_PASSWORD);
        if ($username === '' || $password === '') {
            throw new RuntimeException('Openprovider username and password are not set.');
        }

        $response = Http::acceptJson()
            ->asJson()
            ->timeout(30)
            ->post($this->url('auth/login'), [
                'username' => $username,
                'password' => $password,
            ]);

        if ($response->failed()) {
            throw new RuntimeException($this->failure($response->status(), (string) $response->json('desc')));
        }

        $token = (string) $response->json('data.token');
        if ($token === '') {
            throw new RuntimeException('Openprovider login returned no token.');
        }

        // Their tokens last hours; a short cache keeps a long sync on one login
        // without holding a stale token after a password change.
        Cache::put($cacheKey, $token, now()->addMinutes(30));

        return $token;
    }

    public function forgetToken(): void
    {
        Cache::forget('openprovider.access_token');
    }

    /**
     * Every domain on the account, oldest page first.
     *
     * @return list<array<string, mixed>>
     */
    public function domains(): array
    {
        return $this->paginate('domains', ['with_additional_data' => 'false']);
    }

    /**
     * TLDs with reseller and product prices.
     *
     * Asked for in batches: the filter is a repeated query parameter, and a
     * portfolio with a hundred extensions would otherwise build one very long
     * URL.
     *
     * @param  list<string>  $extensions  Without the leading dot, e.g. ['nl', 'com'].
     * @return list<array<string, mixed>>
     */
    public function tlds(array $extensions): array
    {
        $extensions = array_values(array_unique(array_filter($extensions)));
        if ($extensions === []) {
            return [];
        }

        $out = [];
        foreach (array_chunk($extensions, self::EXTENSIONS_PER_REQUEST) as $chunk) {
            foreach ($this->paginate('tlds', [
                'with_price' => 'true',
                'extensions' => $chunk,
            ]) as $tld) {
                $out[] = $tld;
            }
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $query
     * @return list<array<string, mixed>>
     */
    private function paginate(string $path, array $query = []): array
    {
        $out = [];
        $offset = 0;

        do {
            $url = $this->url($path).'?'.$this->queryString([
                ...$query,
                'limit' => self::PAGE_SIZE,
                'offset' => $offset,
            ]);

            $response = $this->request()->get($url);

            if ($response->status() === 401) {
                // The cached token outlived its welcome; one retry on a fresh one.
                $this->forgetToken();
                $response = $this->request()->get($url);
            }

            if ($response->failed()) {
                throw new RuntimeException($this->failure($response->status(), (string) $response->json('desc')));
            }

            $results = $response->json('data.results');
            $results = is_array($results) ? $results : [];
            foreach ($results as $row) {
                if (is_array($row)) {
                    $out[] = $row;
                }
            }

            $total = (int) $response->json('data.total', count($out));
            $offset += self::PAGE_SIZE;
        } while ($results !== [] && count($out) < $total);

        return $out;
    }

    /**
     * Openprovider's array filters are `collectionFormat: multi` — the same
     * key repeated. Laravel would send `extensions[0]=nl`, which their API
     * does not recognise as the filter at all, so it quietly answers with
     * something other than what was asked for.
     *
     * @param  array<string, mixed>  $query
     */
    private function queryString(array $query): string
    {
        $pairs = [];

        foreach ($query as $key => $value) {
            foreach (is_array($value) ? $value : [$value] as $item) {
                if ($item === null || $item === '') {
                    continue;
                }
                $pairs[] = rawurlencode($key).'='.rawurlencode((string) $item);
            }
        }

        return implode('&', $pairs);
    }

    private function request(): PendingRequest
    {
        return Http::acceptJson()
            ->asJson()
            ->timeout(60)
            ->withToken($this->token());
    }

    private function url(string $path): string
    {
        return $this->host().'/'.$this->version().'/'.ltrim($path, '/');
    }

    private function failure(int $status, string $description): string
    {
        $hint = $status === 401
            ? ' Check the username and password, and whether this server\'s IP is whitelisted in Openprovider.'
            : '';

        return 'Openprovider request failed (HTTP '.$status.')'
            .($description !== '' ? ': '.$description : '.')
            .$hint;
    }
}
