<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

$tz = config('app.timezone');

// Morning reminders in the configured timezone (APP_TIMEZONE).
Schedule::command('contracts:send-renewal-reminders --days=30')
    ->dailyAt('08:00')
    ->timezone($tz);

Schedule::command('endpoints:send-expiry-reminders')
    ->dailyAt('08:05')
    ->timezone($tz);

Schedule::command('planning:send-deadline-reminders')
    ->dailyAt('08:10')
    ->timezone($tz);

Schedule::command('pax8:sync')
    ->dailyAt('06:30')
    ->timezone($tz);
