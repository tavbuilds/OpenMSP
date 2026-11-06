<?php

namespace App\Console\Commands;

use App\Monitoring\UptimeKuma\UptimeKumaClient;
use App\Monitoring\UptimeKuma\UptimeKumaException;
use App\Monitoring\UptimeKuma\UptimeKumaSync;
use Illuminate\Console\Command;

class SyncUptimeKuma extends Command
{
    protected $signature = 'uptime-kuma:sync {--create-missing : Create endpoints for certificate monitors that do not match yet}';

    protected $description = 'Pull certificate expiry from Uptime Kuma /metrics into endpoints.';

    public function handle(UptimeKumaClient $client, UptimeKumaSync $sync): int
    {
        if (! $client->configured()) {
            $this->warn('Uptime Kuma base URL or API key is not set.');

            return self::SUCCESS;
        }

        try {
            $report = $sync->run((bool) $this->option('create-missing'));
        } catch (UptimeKumaException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info('Uptime Kuma sync finished.');
        $this->line('matched: '.$report['matched'].', updated: '.$report['updated'].', created: '.$report['created'].', unmatched: '.$report['unmatched_count']);

        return self::SUCCESS;
    }
}
