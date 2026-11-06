<?php

namespace App\Monitoring\UptimeKuma;

use App\Support\PlatformSettings;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Uptime Kuma has no REST API for creating monitors. The key from
 * Settings → Security → API Keys is the password for HTTP basic auth
 * on GET /metrics (the username is ignored).
 */
class UptimeKumaClient
{
    public function configured(): bool
    {
        return filled($this->baseUrl()) && filled($this->apiKey());
    }

    public function baseUrl(): ?string
    {
        $url = PlatformSettings::get(PlatformSettings::UPTIME_KUMA_BASE_URL);

        return filled($url) ? rtrim((string) $url, '/') : null;
    }

    public function apiKey(): ?string
    {
        $key = PlatformSettings::get(PlatformSettings::UPTIME_KUMA_API_KEY);

        return filled($key) ? (string) $key : null;
    }

    public function metrics(): string
    {
        if (! $this->configured()) {
            throw new UptimeKumaException('Uptime Kuma base URL and API key are not set.');
        }

        $url = $this->baseUrl().'/metrics';

        try {
            $response = Http::withBasicAuth('openmsp', (string) $this->apiKey())
                ->accept('text/plain')
                ->timeout(10)
                ->connectTimeout(5)
                ->get($url);
        } catch (ConnectionException) {
            throw new UptimeKumaException('Could not reach Uptime Kuma at '.$this->baseUrl().'.');
        }

        if ($response->status() === 401 || $response->status() === 403) {
            throw new UptimeKumaException(
                'Uptime Kuma rejected the API key (HTTP '.$response->status().'). Use the key from Settings → Security → API Keys. It is the password for basic auth on /metrics.'
            );
        }

        if (! $response->successful()) {
            throw new UptimeKumaException('Uptime Kuma returned HTTP '.$response->status().'.');
        }

        $body = $response->body();
        if (strlen($body) > 2_000_000) {
            throw new UptimeKumaException('Uptime Kuma metrics response is too large.');
        }

        return $body;
    }

    /**
     * @return array{ok: true, cert_monitors: int, base_url: string}
     */
    public function test(): array
    {
        $body = $this->metrics();

        return [
            'ok' => true,
            'cert_monitors' => count(PrometheusCertMetrics::certificateDays($body)),
            'base_url' => (string) $this->baseUrl(),
        ];
    }
}
