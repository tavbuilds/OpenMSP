<?php

namespace App\Distributors\Pax8;

use App\Support\PlatformSettings;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class Pax8Client
{
    public const SOURCE = 'pax8';

    public function configured(): bool
    {
        return filled(PlatformSettings::get(PlatformSettings::PAX8_CLIENT_ID))
            && filled(PlatformSettings::get(PlatformSettings::PAX8_CLIENT_SECRET));
    }

    public function token(): string
    {
        $cacheKey = 'pax8.access_token';
        $cached = Cache::get($cacheKey);
        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $id = (string) PlatformSettings::get(PlatformSettings::PAX8_CLIENT_ID);
        $secret = (string) PlatformSettings::get(PlatformSettings::PAX8_CLIENT_SECRET);
        if ($id === '' || $secret === '') {
            throw new RuntimeException('Pax8 Client ID and secret are not set.');
        }

        $audience = (string) PlatformSettings::get(PlatformSettings::PAX8_AUDIENCE, 'https://api.pax8.com');

        $response = Http::acceptJson()
            ->asJson()
            ->timeout(30)
            ->post('https://api.pax8.com/v1/token', [
                'grant_type' => 'client_credentials',
                'audience' => $audience,
                'client_id' => $id,
                'client_secret' => $secret,
            ]);

        if ($response->failed()) {
            throw new RuntimeException('Pax8 token request failed (HTTP '.$response->status().'). Check Client ID and secret.');
        }

        $token = (string) $response->json('access_token');
        if ($token === '') {
            throw new RuntimeException('Pax8 token response did not include access_token.');
        }

        $ttl = max(60, (int) $response->json('expires_in', 3600) - 60);
        Cache::put($cacheKey, $token, $ttl);

        return $token;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function companies(): array
    {
        return $this->paginate('/companies');
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function products(): array
    {
        return $this->paginate('/products');
    }

    /**
     * @return array<string, mixed>
     */
    public function product(string $id): array
    {
        return $this->http()->get('/products/'.$id)->throw()->json() ?? [];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function productPricing(string $productId): array
    {
        $json = $this->http()->get('/products/'.$productId.'/pricing')->throw()->json();
        if (is_array($json) && array_is_list($json)) {
            return $json;
        }

        $content = is_array($json) ? ($json['content'] ?? []) : [];

        return is_array($content) ? array_values($content) : [];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function subscriptions(): array
    {
        return $this->paginate('/subscriptions');
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function paginate(string $path): array
    {
        $page = 0;
        $size = 200;
        $all = [];

        do {
            $json = $this->http()->get($path, ['page' => $page, 'size' => $size])->throw()->json();
            $content = is_array($json) ? ($json['content'] ?? $json) : [];
            if (! is_array($content)) {
                break;
            }
            if (! array_is_list($content) && isset($json['content']) === false) {
                $content = [$content];
            }
            foreach ($content as $row) {
                if (is_array($row)) {
                    $all[] = $row;
                }
            }

            $pageMeta = is_array($json) ? ($json['page'] ?? []) : [];
            $totalPages = (int) ($pageMeta['totalPages'] ?? 1);
            $page++;
        } while ($page < $totalPages);

        return $all;
    }

    private function http(): PendingRequest
    {
        return Http::baseUrl('https://api.pax8.com/v1')
            ->acceptJson()
            ->withToken($this->token())
            ->timeout(60);
    }
}
