<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// The server invokes schedule:run each minute. These state transitions are
// idempotent and use conditional updates to remain safe across overlapping hosts.
Schedule::command('subscriptions:expire')->everyMinute()->withoutOverlapping();
Schedule::command('routers:mark-offline')->everyMinute()->withoutOverlapping();
