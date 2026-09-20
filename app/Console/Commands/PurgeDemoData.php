<?php

namespace App\Console\Commands;

use App\Support\DemoData;
use Illuminate\Console\Command;

class PurgeDemoData extends Command
{
    protected $signature = 'demo:purge';

    protected $description = 'Deletes every record with is_demo=1.';

    public function handle(): int
    {
        $n = DemoData::purge();
        $this->info("{$n} demo record(s) deleted.");

        return self::SUCCESS;
    }
}
