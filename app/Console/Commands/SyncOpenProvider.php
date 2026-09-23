<?php

namespace App\Console\Commands;

use App\Registrars\OpenProvider\OpenProviderClient;
use App\Registrars\OpenProvider\OpenProviderSync;
use Illuminate\Console\Command;
use Throwable;

class SyncOpenProvider extends Command
{
    protected $signature = 'openprovider:sync {--test : Only request a token}';

    protected $description = 'Pull domains and per-extension purchase prices from Openprovider.';

    public function handle(OpenProviderClient $client, OpenProviderSync $sync): int
    {
        if (! $client->configured()) {
            $this->warn('Openprovider credentials are not set (Catalog → Openprovider).');

            return self::SUCCESS;
        }

        try {
            if ($this->option('test')) {
                $client->token();
                $this->info('Openprovider token ok.');

                return self::SUCCESS;
            }

            $report = $sync->run();
            $this->info('Openprovider sync finished.');
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
