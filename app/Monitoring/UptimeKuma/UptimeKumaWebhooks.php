<?php

namespace App\Monitoring\UptimeKuma;

use App\Models\Endpoint;
use App\Support\PlatformSettings;
use Illuminate\Support\Collection;

/**
 * Registers each endpoint webhook as a JSON notification in Uptime Kuma
 * and attaches it to the monitor with the same URL or hostname.
 *
 * When that monitor does not exist yet, this creates an HTTPS monitor for
 * the endpoint URL, hostname, or a domain-like name. Certificate and domain
 * expiry notifications on that monitor are turned off. OpenMSP sends those
 * reminders. Other Kuma channels are left as they are, so a downtime
 * notification the operator added stays. Two monitors for the same address
 * are left alone.
 *
 * Deleting an endpoint removes that monitor and the OpenMSP webhook.
 * Two matches, or a Kuma error, leave the endpoint in place.
 */
class UptimeKumaWebhooks
{
    private const ATTEMPTS = 3;

    public function __construct(private UptimeKumaClient $client) {}

    /**
     * @param  list<int>|null  $endpointIds  Only these endpoints. Null means no id filter. An empty list sends nothing.
     * @return array<string, mixed>
     */
    public function push(?int $endpointId = null, bool $failedOnly = false, ?array $endpointIds = null): array
    {
        $username = PlatformSettings::get(PlatformSettings::UPTIME_KUMA_USERNAME);
        $password = PlatformSettings::get(PlatformSettings::UPTIME_KUMA_PASSWORD);
        if (! filled($this->client->baseUrl()) || ! filled($username) || ! filled($password)) {
            throw new UptimeKumaException('Set the Uptime Kuma base URL, username and password. The API key only reads /metrics and cannot add a webhook.');
        }

        // A single id wins over a selection, matching the API.
        if ($endpointId !== null) {
            $endpointIds = null;
        } elseif (is_array($endpointIds)) {
            $endpointIds = array_values(array_unique(array_filter(
                array_map(static fn (mixed $id): int => (int) $id, $endpointIds),
                static fn (int $id): bool => $id > 0,
            )));
            if ($endpointIds === []) {
                return $this->emptyReport();
            }
        }

        $query = Endpoint::query()->orderBy('id');
        if ($endpointId !== null) {
            $query->whereKey($endpointId);
        } elseif (is_array($endpointIds)) {
            $query->whereKey($endpointIds);
        } elseif ($failedOnly) {
            $query->whereNotNull('kuma_push_failed_at');
        }
        $endpoints = $query->get();
        // A retry of nothing has no reason to open Kuma. A full push still
        // logs in, so a bad password or two-factor prompt is reported even
        // when the catalog is empty.
        if ($endpoints->isEmpty() && ($failedOnly || is_array($endpointIds))) {
            return $this->emptyReport();
        }

        $socket = new KumaSocket((string) $this->client->baseUrl());
        $socket->login((string) $username, (string) $password);

        if ($endpoints->isEmpty()) {
            return $this->emptyReport();
        }

        $monitors = $this->monitors($socket);
        $notifications = $this->notifications($socket);

        $attached = 0;
        $created = 0;
        $updated = 0;
        $unchanged = 0;
        $monitorsCreated = 0;
        $unmatched = [];
        $ambiguous = [];
        $failed = [];

        foreach ($endpoints as $endpoint) {
            $outcome = null;
            $lastError = null;
            for ($attempt = 1; $attempt <= self::ATTEMPTS; $attempt++) {
                try {
                    $outcome = $this->pushOne($socket, $endpoint, $monitors, $notifications);
                    $lastError = null;
                    break;
                } catch (UptimeKumaException $e) {
                    $lastError = $e;
                }
            }

            if ($lastError !== null) {
                $this->markFailed($endpoint, $lastError);
                $failed[] = $endpoint->name.': '.$lastError->getMessage();

                continue;
            }

            $this->markPushed($endpoint);
            $created += $outcome['created'];
            $updated += $outcome['updated'];
            $monitorsCreated += $outcome['monitors_created'];

            if ($outcome['match'] === 'ambiguous') {
                $ambiguous[] = $endpoint->name;

                continue;
            }

            if ($outcome['match'] === 'none') {
                $unmatched[] = $endpoint->name;

                continue;
            }

            $outcome['changed'] ? $attached++ : $unchanged++;
        }

        $expiriesUpdated = $this->readExpiries($endpoints);

        return [
            'endpoints' => $endpoints->count(),
            'attached' => $attached,
            'created' => $created,
            'updated' => $updated,
            'unchanged' => $unchanged,
            'monitors_created' => $monitorsCreated,
            'unmatched_count' => count($unmatched),
            'unmatched' => array_slice($unmatched, 0, 50),
            'ambiguous_count' => count($ambiguous),
            'ambiguous' => array_slice($ambiguous, 0, 50),
            'failed_count' => count($failed),
            'failed' => array_slice($failed, 0, 50),
            'expiries_updated' => $expiriesUpdated,
        ];
    }

    /**
     * Remove the monitor and the OpenMSP webhook for this endpoint.
     * A missing dashboard login skips Kuma. The caller still deletes the row.
     */
    public function remove(Endpoint $endpoint): void
    {
        $username = PlatformSettings::get(PlatformSettings::UPTIME_KUMA_USERNAME);
        $password = PlatformSettings::get(PlatformSettings::UPTIME_KUMA_PASSWORD);
        if (! filled($this->client->baseUrl()) || ! filled($username) || ! filled($password)) {
            return;
        }

        $socket = new KumaSocket((string) $this->client->baseUrl());
        $socket->login((string) $username, (string) $password);

        $monitors = $this->monitorsFor($this->monitors($socket), $endpoint);
        $notification = $this->existingNotification($this->notifications($socket), $endpoint);
        if ($monitors->count() > 1) {
            throw new UptimeKumaException('Uptime Kuma has more than one monitor for '.$endpoint->name.'. None was removed.');
        }

        if ($monitors->isNotEmpty()) {
            $monitorId = (int) ($monitors->first()['id'] ?? 0);
            if ($monitorId < 1) {
                throw new UptimeKumaException('Uptime Kuma did not return the monitor id.');
            }

            $this->requireOk(
                $socket->emit('deleteMonitor', $monitorId),
                'Uptime Kuma did not delete the monitor.',
            );
        }
        if ($notification === null) {
            return;
        }

        $notificationId = (int) ($notification['id'] ?? 0);
        if ($notificationId < 1) {
            throw new UptimeKumaException('Uptime Kuma returned a webhook without an id.');
        }

        $this->requireOk(
            $socket->emit('deleteNotification', $notificationId),
            'Uptime Kuma did not delete the webhook.',
        );
    }

    /**
     * @param  Collection<int, Endpoint>  $endpoints
     */
    private function readExpiries(Collection $endpoints): int
    {
        if ($endpoints->isEmpty() || ! $this->client->configured()) {
            return 0;
        }

        try {
            return app(UptimeKumaSync::class)->applyTo($endpoints, $this->client->metrics());
        } catch (UptimeKumaException) {
            return 0;
        }
    }

    /**
     * @param  array<string, mixed>  $report
     */
    public static function summary(array $report): string
    {
        $text = __(':attached attached, :created new, :updated replaced, :unchanged already in place, :monitors monitors created, :unmatched without a URL, :ambiguous ambiguous.', [
            'attached' => $report['attached'],
            'created' => $report['created'],
            'updated' => $report['updated'],
            'unchanged' => $report['unchanged'],
            'monitors' => $report['monitors_created'],
            'unmatched' => $report['unmatched_count'],
            'ambiguous' => $report['ambiguous_count'],
        ]);

        if (($report['failed_count'] ?? 0) > 0) {
            $text .= ' '.__(':failed could not be completed.', ['failed' => $report['failed_count']]);
        }

        return $text;
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyReport(): array
    {
        return [
            'endpoints' => 0,
            'attached' => 0,
            'created' => 0,
            'updated' => 0,
            'unchanged' => 0,
            'monitors_created' => 0,
            'unmatched_count' => 0,
            'unmatched' => [],
            'ambiguous_count' => 0,
            'ambiguous' => [],
            'failed_count' => 0,
            'failed' => [],
            'expiries_updated' => 0,
        ];
    }

    private function markFailed(Endpoint $endpoint, UptimeKumaException $e): void
    {
        $endpoint->forceFill([
            'kuma_push_error' => mb_substr($e->getMessage(), 0, 1000),
            'kuma_push_failed_at' => now(),
        ])->save();
    }

    private function markPushed(Endpoint $endpoint): void
    {
        if ($endpoint->kuma_push_failed_at === null && $endpoint->kuma_push_error === null) {
            return;
        }

        $endpoint->forceFill([
            'kuma_push_error' => null,
            'kuma_push_failed_at' => null,
        ])->save();
    }

    /**
     * @param  list<array<string, mixed>>  $monitors
     * @param  list<array<string, mixed>>  $notifications
     * @return array{match: string, created: int, updated: int, monitors_created: int, changed: bool}
     */
    private function pushOne(KumaSocket $socket, Endpoint $endpoint, array &$monitors, array &$notifications): array
    {
        $created = 0;
        $updated = 0;
        $existing = $this->existingNotification($notifications, $endpoint);
        $url = $endpoint->webhookUrl();

        if ($existing === null) {
            $saved = $this->requireOk($socket->emit('addNotification', $this->payload($endpoint), null), 'Uptime Kuma did not save the webhook.');
            $id = (int) ($saved['id'] ?? 0);
            if ($id < 1) {
                throw new UptimeKumaException('Uptime Kuma saved a webhook without an id.');
            }
            $existing = ['id' => $id, 'name' => $this->notificationName($endpoint), 'config' => ['webhookURL' => $url]];
            $notifications[] = $existing;
            $created = 1;
        } elseif ($this->webhookOf($existing) !== $url) {
            $this->requireOk(
                $socket->emit('addNotification', $this->payload($endpoint), (int) $existing['id']),
                'Uptime Kuma did not update the webhook.',
            );
            $existing['config'] = ['webhookURL' => $url];
            $updated = 1;
        }

        $candidates = $this->candidates($monitors, $endpoint);
        if ($candidates->count() > 1) {
            return [
                'match' => 'ambiguous',
                'created' => $created,
                'updated' => $updated,
                'monitors_created' => 0,
                'changed' => false,
            ];
        }

        $monitorsCreated = 0;
        if ($candidates->isEmpty()) {
            $monitorUrl = $this->monitorUrl($endpoint);
            if ($monitorUrl === null) {
                return [
                    'match' => 'none',
                    'created' => $created,
                    'updated' => $updated,
                    'monitors_created' => 0,
                    'changed' => false,
                ];
            }

            $monitorId = $this->createMonitor($socket, $endpoint, $monitorUrl, (int) $existing['id']);
            $monitors[] = [
                'id' => $monitorId,
                'name' => $endpoint->name,
                'url' => $monitorUrl,
            ];
            $monitorsCreated = 1;
        } else {
            $monitorId = (int) $candidates->first()['id'];
        }

        $changed = $this->attach($socket, $monitorId, (int) $existing['id']);

        return [
            'match' => 'one',
            'created' => $created,
            'updated' => $updated,
            'monitors_created' => $monitorsCreated,
            'changed' => $changed || $created === 1 || $updated === 1 || $monitorsCreated === 1,
        ];
    }

    /**
     * Kuma 2.x `add`. The handler stringifies the list fields before it
     * stores the bean, and it links notificationIDList itself.
     */
    private function createMonitor(KumaSocket $socket, Endpoint $endpoint, string $url, int $notificationId): int
    {
        $saved = $this->requireOk($socket->emit('add', [
            'type' => 'http',
            'name' => mb_substr((string) $endpoint->name, 0, 255),
            'url' => $url,
            'method' => 'GET',
            'interval' => 60,
            'retryInterval' => 60,
            'resendInterval' => 0,
            'maxretries' => 0,
            'active' => true,
            'ignoreTls' => false,
            'upsideDown' => false,
            'expiryNotification' => false,
            'domainExpiryNotification' => false,
            'maxredirects' => 10,
            'accepted_statuscodes' => ['200-299'],
            'notificationIDList' => (object) [(string) $notificationId => true],
            'kafkaProducerBrokers' => [],
            'kafkaProducerSaslOptions' => ['mechanism' => 'None'],
            'conditions' => [],
            'rabbitmqNodes' => [],
        ]), 'Uptime Kuma did not add the monitor.');

        $id = (int) ($saved['monitorID'] ?? $saved['monitorId'] ?? 0);
        if ($id < 1) {
            throw new UptimeKumaException('Uptime Kuma added a monitor without an id.');
        }

        return $id;
    }

    /**
     * An HTTP(S) URL Kuma can check. A bare hostname is prefixed with https.
     * The name is used only when it is itself a hostname, so a domain typed
     * in as the endpoint name still becomes a monitor.
     */
    private function monitorUrl(Endpoint $endpoint): ?string
    {
        foreach ([$endpoint->url, $endpoint->hostname, $this->domainName($endpoint->name)] as $candidate) {
            $url = $this->httpUrl($candidate);
            if ($url !== null) {
                return $url;
            }
        }

        return null;
    }

    private function domainName(mixed $name): ?string
    {
        $name = trim((string) $name);

        return $this->isHostname($name) ? $name : null;
    }

    private function httpUrl(mixed $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        if (! preg_match('#^[a-z][a-z0-9+.-]*://#i', $value)) {
            $value = 'https://'.$value;
        }

        $parts = parse_url($value);
        if (! is_array($parts) || empty($parts['host'])) {
            return null;
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        if (! in_array($scheme, ['http', 'https'], true)) {
            return null;
        }

        $host = strtolower((string) $parts['host']);
        if (! $this->isHostname($host) && ! filter_var($host, FILTER_VALIDATE_IP)) {
            return null;
        }

        $port = isset($parts['port']) ? ':'.$parts['port'] : '';
        $path = isset($parts['path']) ? rtrim((string) $parts['path'], '/') : '';
        $query = isset($parts['query']) ? '?'.$parts['query'] : '';

        return $scheme.'://'.$host.$port.$path.$query;
    }

    private function isHostname(string $value): bool
    {
        $value = strtolower(trim($value));
        if ($value === '' || str_contains($value, ' ') || str_contains($value, '/')) {
            return false;
        }

        return (bool) preg_match('/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,}$/', $value);
    }

    private function attach(KumaSocket $socket, int $monitorId, int $notificationId): bool
    {
        $reply = $this->requireOk($socket->emit('getMonitor', $monitorId), 'Uptime Kuma did not return the monitor.');
        $monitor = $reply['monitor'] ?? null;
        if (! is_array($monitor)) {
            throw new UptimeKumaException('Uptime Kuma did not return the monitor.');
        }

        $ids = $this->enabledIds($monitor['notificationIDList'] ?? []);
        $already = in_array($notificationId, $ids, true);
        $expiryOn = $this->flagOn($monitor, 'expiryNotification');
        $domainOn = $this->flagOn($monitor, 'domainExpiryNotification');
        if ($already && ! $expiryOn && ! $domainOn) {
            return false;
        }

        $map = [];
        foreach ($ids as $id) {
            $map[(string) $id] = true;
        }
        $map[(string) $notificationId] = true;
        $monitor['notificationIDList'] = (object) $map;

        if (array_key_exists('expiryNotification', $monitor)) {
            $monitor['expiryNotification'] = false;
        }
        if (array_key_exists('domainExpiryNotification', $monitor)) {
            $monitor['domainExpiryNotification'] = false;
        }

        $this->requireOk($socket->emit('editMonitor', $monitor), 'Uptime Kuma did not update the monitor.');

        return true;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function monitors(KumaSocket $socket): array
    {
        $list = $socket->take('monitorList');
        if ($list === null) {
            $list = $socket->emit('getMonitorList');
        }

        return $this->rows($list, 'monitorList');
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function notifications(KumaSocket $socket): array
    {
        $list = $socket->take('notificationList');
        if ($list === null) {
            $list = $socket->emit('getNotificationList');
        }

        return $this->rows($list, 'notificationList');
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function rows(mixed $list, string $key): array
    {
        if (is_array($list) && isset($list[$key]) && is_array($list[$key])) {
            $list = $list[$key];
        }
        if (! is_array($list)) {
            return [];
        }

        $rows = [];
        foreach ($list as $id => $row) {
            if (! is_array($row)) {
                continue;
            }
            if (! isset($row['id']) && is_numeric($id)) {
                $row['id'] = (int) $id;
            }
            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * Same address match as a push. A name-only endpoint is included, because
     * that is the HTTPS monitor push creates for a domain-like name.
     *
     * @param  list<array<string, mixed>>  $monitors
     * @return Collection<int, array<string, mixed>>
     */
    private function monitorsFor(array $monitors, Endpoint $endpoint): Collection
    {
        $candidates = $this->candidates($monitors, $endpoint);
        if ($candidates->isNotEmpty()) {
            return $candidates;
        }

        $url = $this->normaliseUrl($this->monitorUrl($endpoint));
        if ($url === null) {
            return new Collection;
        }

        return collect($monitors)
            ->filter(fn (array $monitor): bool => $this->normaliseUrl($monitor['url'] ?? null) === $url)
            ->values();
    }

    /**
     * @param  list<array<string, mixed>>  $monitors
     * @return Collection<int, array<string, mixed>>
     */
    private function candidates(array $monitors, Endpoint $endpoint): Collection
    {
        $url = $this->normaliseUrl($endpoint->url);
        if ($url !== null) {
            $byUrl = collect($monitors)
                ->filter(fn (array $monitor) => $this->normaliseUrl($monitor['url'] ?? null) === $url)
                ->values();
            if ($byUrl->isNotEmpty()) {
                return $byUrl;
            }
        }

        $host = $this->hostOf($endpoint->hostname, $endpoint->url);
        if ($host !== null) {
            $byHost = collect($monitors)
                ->filter(function (array $monitor) use ($host) {
                    $monitorHost = strtolower(trim((string) ($monitor['hostname'] ?? '')));

                    return $monitorHost === $host || $this->hostOf(null, $monitor['url'] ?? null) === $host;
                })
                ->values();
            if ($byHost->isNotEmpty()) {
                return $byHost;
            }
        }

        return new Collection;
    }

    /**
     * @param  list<array<string, mixed>>  $notifications
     * @return array<string, mixed>|null
     */
    private function existingNotification(array $notifications, Endpoint $endpoint): ?array
    {
        $url = $endpoint->webhookUrl();
        $prefix = 'OpenMSP '.$endpoint->id.':';
        $byName = null;

        foreach ($notifications as $notification) {
            if ($this->webhookOf($notification) === $url) {
                return $notification;
            }
            if (str_starts_with((string) ($notification['name'] ?? ''), $prefix)) {
                $byName = $notification;
            }
        }

        return $byName;
    }

    /**
     * @param  array<string, mixed>  $notification
     */
    private function webhookOf(array $notification): ?string
    {
        $config = $notification['config'] ?? null;
        if (is_string($config)) {
            $config = json_decode($config, true);
        }
        if (! is_array($config)) {
            $config = $notification;
        }

        $url = $config['webhookURL'] ?? $config['webhookUrl'] ?? null;

        return is_string($url) && $url !== '' ? $url : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Endpoint $endpoint): array
    {
        return [
            'name' => $this->notificationName($endpoint),
            'type' => 'webhook',
            'isDefault' => false,
            'applyExisting' => false,
            'webhookURL' => $endpoint->webhookUrl(),
            'webhookContentType' => 'json',
        ];
    }

    private function notificationName(Endpoint $endpoint): string
    {
        return mb_substr('OpenMSP '.$endpoint->id.': '.$endpoint->name, 0, 120);
    }

    /**
     * @return list<int>
     */
    private function enabledIds(mixed $list): array
    {
        if (is_string($list)) {
            $list = json_decode($list, true);
        }
        if (! is_array($list)) {
            return [];
        }

        $ids = [];
        foreach ($list as $id => $on) {
            if ($on) {
                $ids[] = (int) $id;
            }
        }

        return $ids;
    }

    /**
     * @param  array<string, mixed>  $monitor
     */
    private function flagOn(array $monitor, string $key): bool
    {
        if (! array_key_exists($key, $monitor)) {
            return false;
        }

        return filter_var($monitor[$key], FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * @param  array<string, mixed>  $result
     * @return array<string, mixed>
     */
    private function requireOk(array $result, string $fallback): array
    {
        if (($result['ok'] ?? false) !== true) {
            $message = trim((string) ($result['msg'] ?? ''));

            throw new UptimeKumaException($message !== '' ? $message : $fallback);
        }

        return $result;
    }

    private function hostOf(mixed $hostname, mixed $url): ?string
    {
        $host = strtolower(trim((string) $hostname));
        if ($host !== '') {
            return $host;
        }

        $parsed = parse_url(trim((string) $url), PHP_URL_HOST);

        return is_string($parsed) && $parsed !== '' ? strtolower($parsed) : null;
    }

    private function normaliseUrl(mixed $url): ?string
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
