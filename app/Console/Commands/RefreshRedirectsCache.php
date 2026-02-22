<?php

namespace App\Console\Commands;

use App\Classes\Business\RedirectBusiness;
use Illuminate\Console\Command;

final class RefreshRedirectsCache extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:refresh-redirects-cache';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Refresh the active redirects cache from the database.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Refreshing redirects cache...');

        RedirectBusiness::refreshCache();

        $this->info('Redirects cache refreshed successfully!');

        return self::SUCCESS;
    }
}
