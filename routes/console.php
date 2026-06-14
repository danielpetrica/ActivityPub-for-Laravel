<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('app:refresh-redirects-cache')->twiceDaily(1, 13);

Schedule::command('app:cleanup-og-images')->daily();

Schedule::command('app:refresh-popular-action-versions')->daily();
