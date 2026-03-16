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

// Room auto-termination: free seats / end rooms when heartbeat older than 60s (app kill, disconnect)
Schedule::command('rooms:cleanup-stale-heartbeats')->everyMinute();

// API cache: flush static/catalog cache hourly so admin updates (countries, FAQ, themes, packages) propagate
Schedule::command('cache:flush-api --force')->hourly();

// API cache warmup: keep hot feed/catalog caches populated ahead of traffic.
Schedule::command('cache:warm')->everyFiveMinutes();
