<?php

namespace App\Monitoring\UptimeKuma;

use App\Enums\EndpointKind;
use App\Enums\EndpointSource;
use App\Models\Endpoint;
use App\Support\PlatformSettings;
use Illuminate\Support\Collection;

/**
 * Pulls certificate days from Uptime Kuma and writes them onto endpoints.
 *
 * Match order is URL, then hostname. A monitor that matches nothing is left
 * alone unless the caller asks for missing endpoints to be created. A
 * nightly run never creates: a renamed monitor should not grow a duplicate.
 */
class UptimeKumaSync
{
    public function __construct(private UptimeKumaClient $client) {}

    /**
     * @return array<string, mixed>
     */
    public function run(bool $createMissing = false): array
    {
        try {
            $report = $this->import($this->client->metrics(), $createMissing);
        } catch (UptimeKumaException $e) {
            PlatformSettings::set(PlatformSettings::UPTIME_KUMA_LAST_ERROR, $e->getMessage());

            throw $e;
        }

        PlatformSettings::set(PlatformSettings::UPTIME_KUMA_LAST_SYNC_AT, now()->toIso8601String());
        PlatformSettings::set(PlatformSettings::UPTIME_KUMA_LAST_SYNC_REPORT, json_encode($report));
        PlatformSettings::forget(PlatformSettings::UPTIME_KUMA_LAST_ERROR);

        return $report;
    }

    /**
     * @return array<string, mixed>
     */
    public function import(string $exposition, bool $createMissing = false): array
    {
        $samples = PrometheusCertMetrics::certificateDays($exposition);
        /** @var Collection<int, Endpoint> $endpoints */
        $endpoints = Endpoint::query()->orderBy('id')->get();

        $matched = 0;
        $updated = 0;
        $unchanged = 0;
        $created = 0;
        $unmatched = [];
        $ambiguous = [];

        foreach ($samples as $sample) {
            $candidates = $this->candidates($endpoints, $sample);

            if ($candidates->count() > 1) {
                $ambiguous[] = $this->sampleRow($sample);

                continue;
            }

            if ($candidates->count() === 1) {
                $matched++;
                $endpoint = $candidates->first();
                $changed = $endpoint->applyObservedExpiry(
                    now()->addDays($sample['days_remaining']),
                    EndpointSource::UptimeKuma,
                );
                $changed ? $updated++ : $unchanged++;

                continue;
            }

            if (! $createMissing) {
                $unmatched[] = $this->sampleRow($sample);

                continue;
            }

            $endpoint = $this->createEndpoint($sample);
            $endpoints->push($endpoint);
            $created++;
        }

        return [
            'cert_monitors' => count($samples),
            'matched' => $matched,
            'updated' => $updated,
            'unchanged' => $unchanged,
            'created' => $created,
            'unmatched_count' => count($unmatched),
            'unmatched' => array_slice($unmatched, 0, 50),
            'ambiguous_count' => count($ambiguous),
            'ambiguous' => array_slice($ambiguous, 0, 50),
        ];
    }

    /**
     * Write certificate days onto the given endpoints. A push calls this
     * immediately, so the expiry does not wait for the nightly run.
     *
     * @param  Collection<int, Endpoint>  $endpoints
     */
    public function applyTo(Collection $endpoints, string $exposition): int
    {
        $updated = 0;

        foreach (PrometheusCertMetrics::certificateDays($exposition) as $sample) {
            $candidates = $this->candidates($endpoints, $sample);
            if ($candidates->count() !== 1) {
                continue;
            }

            $changed = $candidates->first()->applyObservedExpiry(
                now()->addDays($sample['days_remaining']),
                EndpointSource::UptimeKuma,
            );
            if ($changed) {
                $updated++;
            }
        }

        return $updated;
    }

    /**
     * @param  Collection<int, Endpoint>  $endpoints
     * @param  array{monitor_name: string, monitor_type: string, monitor_url: string, monitor_hostname: string, monitor_port: string, days_remaining: int}  $sample
     * @return Collection<int, Endpoint>
     */
    private function candidates(Collection $endpoints, array $sample): Collection
    {
        $url = $this->normaliseUrl($sample['monitor_url']);
        if ($url !== null) {
            $byUrl = $endpoints
                ->filter(fn (Endpoint $endpoint) => $this->normaliseUrl($endpoint->url) === $url)
                ->values();
            if ($byUrl->isNotEmpty()) {
                return $byUrl;
            }
        }

        $host = $this->hostOf($sample);
        if ($host !== null) {
            $byHost = $endpoints
                ->filter(fn (Endpoint $endpoint) => strtolower((string) $endpoint->hostname) === $host
                    || $this->hostOfUrl($endpoint->url) === $host)
                ->values();
            if ($byHost->isNotEmpty()) {
                return $byHost;
            }
        }

        // A name is only a match when the monitor has no address. "Website"
        // must not attach itself to an unrelated endpoint.
        if ($url !== null || $host !== null) {
            return new Collection;
        }

        $name = trim($sample['monitor_name']);
        if ($name === '') {
            return new Collection;
        }

        return $endpoints
            ->filter(fn (Endpoint $endpoint) => strcasecmp($endpoint->name, $name) === 0)
            ->values();
    }

    /**
     * @param  array{monitor_name: string, monitor_type: string, monitor_url: string, monitor_hostname: string, monitor_port: string, days_remaining: int}  $sample
     */
    private function createEndpoint(array $sample): Endpoint
    {
        $host = $this->hostOf($sample);
        $url = trim($sample['monitor_url']);
        if ($url !== '' && filter_var($url, FILTER_VALIDATE_URL) === false) {
            $url = '';
        }

        $name = trim($sample['monitor_name']);
        if ($name === '') {
            $name = $host ?: 'Certificate';
        }

        $endpoint = Endpoint::create([
            'name' => mb_substr($name, 0, 120),
            'kind' => EndpointKind::Certificate,
            'hostname' => $host,
            'url' => $url === '' ? null : mb_substr($url, 0, 255),
            'expires_at' => now()->addDays($sample['days_remaining'])->toDateString(),
            'source' => EndpointSource::UptimeKuma,
            'last_checked_at' => now(),
        ]);
        $endpoint->forceFill(['last_status' => $endpoint->computeStatus()])->save();

        return $endpoint;
    }

    /**
     * @param  array{monitor_name: string, monitor_type: string, monitor_url: string, monitor_hostname: string, monitor_port: string, days_remaining: int}  $sample
     * @return array{monitor_name: string, monitor_url: string, monitor_hostname: string, days_remaining: int}
     */
    private function sampleRow(array $sample): array
    {
        return [
            'monitor_name' => $sample['monitor_name'],
            'monitor_url' => $sample['monitor_url'],
            'monitor_hostname' => $sample['monitor_hostname'],
            'days_remaining' => $sample['days_remaining'],
        ];
    }

    /**
     * @param  array{monitor_name: string, monitor_url: string, monitor_hostname: string, monitor_port: string, monitor_type?: string, days_remaining: int}  $sample
     */
    private function hostOf(array $sample): ?string
    {
        $host = strtolower(trim($sample['monitor_hostname']));
        if ($host !== '') {
            return $host;
        }

        return $this->hostOfUrl($sample['monitor_url']);
    }

    private function hostOfUrl(?string $url): ?string
    {
        $url = trim((string) $url);
        if ($url === '') {
            return null;
        }

        $host = parse_url($url, PHP_URL_HOST);

        return is_string($host) && $host !== '' ? strtolower($host) : null;
    }

    private function normaliseUrl(?string $url): ?string
    {
        $url = trim((string) $url);
        if ($url === '') {
            return null;
        }

        $parts = parse_url($url);
        if (! is_array($parts) || empty($parts['host'])) {
            return rtrim(strtolower($url), '/');
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? 'https'));
        $host = strtolower((string) $parts['host']);
        $port = isset($parts['port']) ? ':'.$parts['port'] : '';
        $path = isset($parts['path']) ? rtrim((string) $parts['path'], '/') : '';
        $query = isset($parts['query']) ? '?'.$parts['query'] : '';

        return $scheme.'://'.$host.$port.$path.$query;
    }
}
