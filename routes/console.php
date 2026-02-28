<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Auto-missed: mark ringing calls older than 30s as missed and push to caller
Schedule::command('call:mark-missed')->everyMinute();

// Ghost-call prevention: end accepted calls with no heartbeat for 90s; bill and notify
Schedule::command('call:cleanup-stale')->everyMinute();

// Room lifecycle: end rooms with no active host and no active speaker/co_host
Schedule::command('rooms:cleanup-empty')->everyTwoMinutes();
