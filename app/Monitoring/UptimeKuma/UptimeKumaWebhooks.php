<?php

namespace App\Monitoring\UptimeKuma;

use App\Models\Endpoint;
use App\Support\PlatformSettings;
use Illuminate\Support\Collection;

/**
 * Registers each endpoint webhook as a JSON notification in Uptime Kuma
 * and attaches it to the monitor with the same URL or hostname.
 *
 * Certificate and domain expiry notifications on that monitor are turned
 * off. OpenMSP sends those reminders. Other Kuma channels are left as they
 * are, so a downtime notification the operator added stays.
 */
class UptimeKumaWebhooks
{
    public function __construct(private UptimeKumaClient $client) {}

    /**
     * @return array<string, mixed>
     */
    public function push(?int $endpointId = null): array
    {
        $username = PlatformSettings::get(PlatformSettings::UPTIME_KUMA_USERNAME);
        $password = PlatformSettings::get(PlatformSettings::UPTIME_KUMA_PASSWORD);
        if (! filled($this->client->baseUrl()) || ! filled($username) || ! filled($password)) {
            throw new UptimeKumaException('Set the Uptime Kuma base URL, username and password. The API key only reads /metrics and cannot add a webhook.');
        }

        $socket = new KumaSocket((string) $this->client->baseUrl());
        $socket->login((string) $username, (string) $password);

        $monitors = $this->monitors($socket);
        $notifications = $this->notifications($socket);

        $query = Endpoint::query()->orderBy('id');
        if ($endpointId !== null) {
            $query->whereKey($endpointId);
        }
        $endpoints = $query->get();

        $attached = 0;
        $created = 0;
        $updated = 0;
        $unchanged = 0;
        $unmatched = [];
        $ambiguous = [];

        foreach ($endpoints as $endpoint) {
            $outcome = $this->pushOne($socket, $endpoint, $monitors, $notifications);
            $created += $outcome['created'];
            $updated += $outcome['updated'];

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

        return [
            'endpoints' => $endpoints->count(),
            'attached' => $attached,
            'created' => $created,
            'updated' => $updated,
            'unchanged' => $unchanged,
            'unmatched_count' => count($unmatched),
            'unmatched' => array_slice($unmatched, 0, 50),
            'ambiguous_count' => count($ambiguous),
            'ambiguous' => array_slice($ambiguous, 0, 50),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $monitors
     * @param  list<array<string, mixed>>  $notifications
     * @return array{match: string, created: int, updated: int, changed: bool}
     */
    private function pushOne(KumaSocket $socket, Endpoint $endpoint, array $monitors, array &$notifications): array
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
        if ($candidates->count() !== 1) {
            return [
                'match' => $candidates->count() > 1 ? 'ambiguous' : 'none',
                'created' => $created,
                'updated' => $updated,
                'changed' => false,
            ];
        }

        $changed = $this->attach($socket, (int) $candidates->first()['id'], (int) $existing['id']);

        return [
            'match' => 'one',
            'created' => $created,
            'updated' => $updated,
            'changed' => $changed || $created === 1 || $updated === 1,
        ];
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
