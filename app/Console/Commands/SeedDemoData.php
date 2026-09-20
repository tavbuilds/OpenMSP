<?php

namespace App\Console\Commands;

use App\Support\DemoAccount;
use App\Support\DemoData;
use Illuminate\Console\Command;

class SeedDemoData extends Command
{
    protected $signature = 'demo:seed {--login : Also create the view-only test/test account}';

    protected $description = 'Loads sample customers, contracts, and catalog (is_demo=1).';

    public function handle(): int
    {
        if (DemoData::exists()) {
            $this->warn('Demo data is already loaded. Remove it first with demo:purge.');

            return self::SUCCESS;
        }

        DemoData::seed();
        if ($this->option('login')) {
            if (DemoAccount::hasRealOperator()) {
                $this->warn('A real administrator already exists; not creating test/test.');
            } else {
                DemoAccount::ensure();
                $this->info('View-only login created (username test / password test). Documented in README, not shown on the login card.');
            }
        }
        $this->info('Demo data loaded. Remove with php artisan demo:purge or System → Demo data.');

        return self::SUCCESS;
    }
}
