<?php

namespace App\Monitoring\UptimeKuma;

/**
 * Reads certificate expiry out of Uptime Kuma's Prometheus exposition.
 *
 * The API key authenticates GET /metrics and nothing else. Certificate
 * days are the one figure that maps onto an OpenMSP endpoint.
 */
final class PrometheusCertMetrics
{
    /**
     * @return list<array{
     *     monitor_name: string,
     *     monitor_type: string,
     *     monitor_url: string,
     *     monitor_hostname: string,
     *     monitor_port: string,
     *     days_remaining: int
     * }>
     */
    public static function certificateDays(string $exposition): array
    {
        $samples = [];

        foreach (preg_split("/\r\n|\n|\r/", $exposition) ?: [] as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }
            if (! str_starts_with($line, 'monitor_cert_days_remaining')) {
                continue;
            }
            if (! preg_match('/^monitor_cert_days_remaining(?:\{(.*)\})?\s+(\S+)\s*$/', $line, $match)) {
                continue;
            }
            if (! is_numeric($match[2])) {
                continue;
            }

            $days = (int) round((float) $match[2]);
            if ($days < -3650 || $days > 3650) {
                continue;
            }

            $labels = self::labels($match[1] ?? '');
            $samples[] = [
                'monitor_name' => $labels['monitor_name'] ?? '',
                'monitor_type' => $labels['monitor_type'] ?? '',
                'monitor_url' => $labels['monitor_url'] ?? '',
                'monitor_hostname' => $labels['monitor_hostname'] ?? '',
                'monitor_port' => $labels['monitor_port'] ?? '',
                'days_remaining' => $days,
            ];
        }

        return $samples;
    }

    /**
     * @return array<string, string>
     */
    private static function labels(string $raw): array
    {
        if ($raw === '') {
            return [];
        }

        $labels = [];
        preg_match_all('/([A-Za-z_][A-Za-z0-9_]*)="((?:\\\\.|[^"\\\\])*)"/', $raw, $matches, PREG_SET_ORDER);
        foreach ($matches as $match) {
            $labels[$match[1]] = stripcslashes($match[2]);
        }

        return $labels;
    }
}
