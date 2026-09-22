<?php

namespace App\Console\Commands;

use App\Distributors\Pax8\Pax8Client;
use App\Distributors\Pax8\Pax8Sync;
use Illuminate\Console\Command;
use Throwable;

class SyncPax8 extends Command
{
    protected $signature = 'pax8:sync {--test : Only request a token}';

    protected $description = 'Sync Pax8 companies, products, costs and subscriptions into OpenMSP.';

    public function handle(Pax8Client $client, Pax8Sync $sync): int
    {
        if (! $client->configured()) {
            $this->warn('Pax8 credentials are not set (Catalog → Pax8).');

            return self::SUCCESS;
        }

        try {
            if ($this->option('test')) {
                $client->token();
                $this->info('Pax8 token ok.');

                return self::SUCCESS;
            }

            $report = $sync->run();
            $this->info('Pax8 sync finished.');
            foreach ($report->toArray() as $key => $value) {
                if ($key === 'errors') {
                    continue;
                }
                $this->line($key.': '.(is_scalar($value) ? $value : json_encode($value)));
            }
            foreach ($report->errors as $error) {
                $this->error($error);
            }

            return $report->errors === [] ? self::SUCCESS : self::FAILURE;
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }
}
