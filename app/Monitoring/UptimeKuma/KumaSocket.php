<?php

namespace App\Monitoring\UptimeKuma;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Minimal Engine.IO 4 / Socket.IO polling client for Uptime Kuma 2.x.
 *
 * Kuma's API key cannot log in here. The dashboard username and password
 * emit `login`, and every later call is an event with an acknowledgement.
 */
class KumaSocket
{
    private ?string $sid = null;

    private int $ack = 0;

    /** @var list<string> */
    private array $inbox = [];

    /** @var array<string, list<mixed>> */
    private array $events = [];

    public function __construct(private string $baseUrl) {}

    /**
     * @return array<string, mixed>
     */
    public function login(string $username, string $password): array
    {
        $this->open();
        $result = $this->emit('login', [
            'username' => $username,
            'password' => $password,
        ]);

        if (! empty($result['tokenRequired'])) {
            throw new UptimeKumaException('Uptime Kuma asked for a two-factor code. Use a login without two-factor authentication.');
        }

        if (($result['ok'] ?? false) !== true) {
            throw new UptimeKumaException($this->failure($result, 'Uptime Kuma rejected the username or password.'));
        }

        return $result;
    }

    /**
     * @return array<string, mixed>
     */
    public function emit(string $event, mixed ...$args): array
    {
        if ($this->sid === null) {
            throw new UptimeKumaException('Uptime Kuma socket is not open.');
        }

        $id = $this->ack++;
        $packet = '42'.$id.json_encode(
            [$event, ...$args],
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
        );
        $this->post($packet);

        for ($attempt = 0; $attempt < 12; $attempt++) {
            $incoming = $this->nextPacket();
            if (! str_starts_with($incoming, '4')) {
                continue;
            }

            $socket = substr($incoming, 1);
            if (str_starts_with($socket, '3')) {
                $parsed = $this->parseAck($socket);
                if ($parsed['id'] === $id) {
                    // monitorList and notificationList often share this poll
                    // with the acknowledgement. Store them before returning.
                    $this->absorbInbox();

                    return $parsed['body'];
                }

                continue;
            }

            $this->rememberEvent($socket);
        }

        throw new UptimeKumaException('Uptime Kuma did not answer '.$event.'.');
    }

    public function take(string $name): mixed
    {
        if (! isset($this->events[$name]) || $this->events[$name] === []) {
            return null;
        }

        return array_shift($this->events[$name]);
    }

    private function open(): void
    {
        $hello = $this->get($this->url());
        if (! str_starts_with($hello, '0')) {
            throw new UptimeKumaException('Uptime Kuma did not open a socket. Check the base URL.');
        }

        $decoded = json_decode(substr($hello, 1), true);
        $sid = is_array($decoded) ? ($decoded['sid'] ?? null) : null;
        if (! is_string($sid) || $sid === '') {
            throw new UptimeKumaException('Uptime Kuma did not open a socket. Check the base URL.');
        }

        $this->sid = $sid;
        $this->post('40');
        $this->drain();
    }

    private function drain(): void
    {
        foreach ($this->pull() as $packet) {
            if (str_starts_with($packet, '4')) {
                $this->rememberEvent(substr($packet, 1));
            }
        }
    }

    private function nextPacket(): string
    {
        if ($this->inbox === []) {
            $this->inbox = $this->pull();
        }

        if ($this->inbox === []) {
            throw new UptimeKumaException('Uptime Kuma closed the socket.');
        }

        return array_shift($this->inbox);
    }

    /**
     * @return list<string>
     */
    private function pull(): array
    {
        $body = trim($this->get($this->url()));
        if ($body === '' || $body === 'ok') {
            return [];
        }

        $packets = [];
        foreach (explode("\x1e", $body) as $packet) {
            if ($packet === '2') {
                $this->post('3');

                continue;
            }
            if ($packet !== '') {
                $packets[] = $packet;
            }
        }

        return $packets;
    }

    private function url(): string
    {
        $query = 'EIO=4&transport=polling';
        if ($this->sid !== null) {
            $query .= '&sid='.rawurlencode($this->sid);
        }

        return $this->baseUrl.'/socket.io/?'.$query;
    }

    private function get(string $url): string
    {
        return $this->send('get', $url, null);
    }

    private function post(string $body): void
    {
        $reply = trim($this->send('post', $this->url(), $body));
        if ($reply === '' || $reply === 'ok') {
            return;
        }

        foreach (explode("\x1e", $reply) as $packet) {
            if ($packet !== '') {
                $this->inbox[] = $packet;
            }
        }
    }

    private function send(string $method, string $url, ?string $body): string
    {
        try {
            $pending = Http::timeout(15)
                ->connectTimeout(5)
                ->withOptions(['allow_redirects' => false])
                ->withHeaders(['Accept' => 'text/plain']);

            $response = $method === 'post'
                ? $pending->withBody((string) $body, 'text/plain')->post($url)
                : $pending->get($url);
        } catch (ConnectionException) {
            throw new UptimeKumaException('Could not reach Uptime Kuma at '.$this->baseUrl.'.');
        }

        if (! $response->successful()) {
            throw new UptimeKumaException('Uptime Kuma socket returned HTTP '.$response->status().'.');
        }

        $text = $response->body();
        if (strlen($text) > 5_000_000) {
            throw new UptimeKumaException('Uptime Kuma socket response is too large.');
        }

        return $text;
    }

    /**
     * @return array{id: int, body: array<string, mixed>}
     */
    private function parseAck(string $socket): array
    {
        if (! preg_match('/^3(\d+)(.*)$/s', $socket, $matches)) {
            throw new UptimeKumaException('Uptime Kuma sent an acknowledgement OpenMSP could not read.');
        }

        $decoded = json_decode($matches[2], true);
        $body = is_array($decoded) ? ($decoded[0] ?? []) : [];
        if (! is_array($body)) {
            $body = [];
        }

        return ['id' => (int) $matches[1], 'body' => $body];
    }

    private function absorbInbox(): void
    {
        $pending = [];

        while ($this->inbox !== []) {
            $packet = array_shift($this->inbox);
            if (! str_starts_with($packet, '4')) {
                $pending[] = $packet;

                continue;
            }

            $socket = substr($packet, 1);
            if (str_starts_with($socket, '3')) {
                $pending[] = $packet;

                continue;
            }

            $this->rememberEvent($socket);
        }

        $this->inbox = $pending;
    }

    private function rememberEvent(string $socket): void
    {
        if (! str_starts_with($socket, '2')) {
            return;
        }

        $rest = substr($socket, 1);
        if (preg_match('/^\d+\[/', $rest) === 1) {
            $rest = (string) preg_replace('/^\d+/', '', $rest);
        }

        $decoded = json_decode($rest, true);
        if (! is_array($decoded) || ! isset($decoded[0]) || ! is_string($decoded[0])) {
            return;
        }

        $this->events[$decoded[0]][] = $decoded[1] ?? null;
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private function failure(array $result, string $fallback): string
    {
        $message = trim((string) ($result['msg'] ?? ''));

        return $message !== '' ? $message : $fallback;
    }
}
