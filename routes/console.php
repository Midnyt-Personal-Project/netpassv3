<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// The server invokes schedule:run each minute; Laravel runs this task every five minutes.
Schedule::command('subscriptions:expire')->everyFiveMinutes()->withoutOverlapping();
