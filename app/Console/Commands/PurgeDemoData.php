<?php

namespace App\Console\Commands;

use App\Support\DemoData;
use Illuminate\Console\Command;

class PurgeDemoData extends Command
{
    protected $signature = 'demo:purge';

    protected $description = 'Deletes every record with is_demo=1 and retires the Demo data page.';

    public function handle(): int
    {
        $n = DemoData::purge();
        $this->info("{$n} demo record(s) deleted.");
        $this->line('The Demo data page is now hidden. Run demo:seed to bring it back.');

        return self::SUCCESS;
    }
}
