<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Every morning at 08:00, send reminders for upcoming renewals / notice deadlines.
Schedule::command('contracts:send-renewal-reminders --days=30')
    ->dailyAt('08:00')
    ->timezone('Europe/Amsterdam');

Schedule::command('endpoints:send-expiry-reminders')
    ->dailyAt('08:05')
    ->timezone('Europe/Amsterdam');
