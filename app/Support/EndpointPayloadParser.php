<?php

namespace App\Support;

use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * Extract certificate/domain expiry from a monitoring webhook (Uptime Kuma, generic JSON).
 *
 * @phpstan-type Parsed array{expires_at: ?CarbonInterface, hostname: ?string, message: ?string}
 */
final class EndpointPayloadParser
{
    /**
     * @param  array<string, mixed>  $payload
     * @return Parsed
     */
    public static function parse(array $payload): array
    {
        $flat = self::flatten($payload);

        $expires = self::firstDate($flat, [
            'expires_at', 'expire_at', 'expiry', 'expiry_date', 'expires',
            'valid_to', 'validto', 'not_after', 'notafter',
            'cert_expire', 'cert_expiry', 'certificate_expiry', 'certificateexpiry',
            'tls_expire', 'ssl_expire', 'valid_till', 'validtill',
        ]);

        if ($expires === null) {
            $days = self::firstNumber($flat, [
                'days_remaining', 'daysremaining', 'days_until', 'daysuntil',
                'days_til_expiration', 'daystilexpiration', 'days_to_expire', 'daystoexpire',
            ]);
            if ($days !== null) {
                $expires = now()->startOfDay()->addDays($days);
            }
        }

        $message = self::firstString($flat, ['msg', 'message', 'text', 'error']);
        if ($expires === null && is_string($message)) {
            if (preg_match('/expire[sd]?\s+in\s+(\d+)\s+day/i', $message, $m)) {
                $expires = now()->startOfDay()->addDays((int) $m[1]);
            } elseif (preg_match('/expired/i', $message)) {
                $expires = now()->startOfDay()->subDay();
            }
        }

        $hostname = self::firstString($flat, [
            'hostname', 'host', 'hostnameorurl',
        ]);
        if ($hostname === null) {
            $url = self::firstString($flat, ['url', 'monitor_url', 'monitorurl']);
            if (is_string($url) && $url !== '') {
                $host = parse_url($url, PHP_URL_HOST);
                $hostname = is_string($host) ? $host : $url;
            }
        }

        return [
            'expires_at' => $expires,
            'hostname' => $hostname,
            'message' => $message,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public static function flatten(array $payload, string $prefix = ''): array
    {
        $out = [];
        foreach ($payload as $key => $value) {
            $k = strtolower((string) $key);
            $path = $prefix === '' ? $k : $prefix.'.'.$k;
            $out[$path] = $value;
            $out[$k] = $value;
            if (is_array($value)) {
                $out = array_merge($out, self::flatten($value, $path));
            }
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $flat
     * @param  list<string>  $keys
     */
    protected static function firstDate(array $flat, array $keys): ?CarbonInterface
    {
        foreach ($keys as $key) {
            if (! array_key_exists($key, $flat) || $flat[$key] === null || $flat[$key] === '') {
                continue;
            }
            $parsed = self::parseDate($flat[$key]);
            if ($parsed) {
                return $parsed;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $flat
     * @param  list<string>  $keys
     */
    protected static function firstNumber(array $flat, array $keys): ?int
    {
        foreach ($keys as $key) {
            if (! array_key_exists($key, $flat) || $flat[$key] === null || $flat[$key] === '') {
                continue;
            }
            if (is_numeric($flat[$key])) {
                return (int) $flat[$key];
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $flat
     * @param  list<string>  $keys
     */
    protected static function firstString(array $flat, array $keys): ?string
    {
        foreach ($keys as $key) {
            if (! array_key_exists($key, $flat)) {
                continue;
            }
            if (is_string($flat[$key]) && $flat[$key] !== '') {
                return $flat[$key];
            }
        }

        return null;
    }

    protected static function parseDate(mixed $value): ?CarbonInterface
    {
        if ($value instanceof CarbonInterface) {
            return $value;
        }
        if (is_int($value) || (is_string($value) && ctype_digit($value) && strlen($value) >= 10)) {
            $ts = (int) $value;
            if ($ts > 1_000_000_000_000) {
                $ts = (int) floor($ts / 1000);
            }

            return Carbon::createFromTimestamp($ts)->startOfDay();
        }
        if (! is_string($value)) {
            return null;
        }
        try {
            return Carbon::parse($value)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }
}
