<?php

use App\Models\UpstreamApiLog;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Prune upstream API logs older than 6 months (UpstreamApiLog::prunable). Runs off the existing
// every-minute schedule:run cron — no extra prod cron needed.
Schedule::command('model:prune', ['--model' => [UpstreamApiLog::class]])->daily();

// Safety-net status poller: sweep every processing order and pull its DBH status. Mirrors DBH's own
// cron sync — polling survives a queue-worker hiccup and never gives up after a fixed try count.
Schedule::command('orders:poll-processing')->everyMinute()->withoutOverlapping();

// Drain the queue on shared hosting without a persistent worker: process pending jobs (receipt
// emails, the instant poll) then exit. --max-time caps a run; withoutOverlapping stops stacking.
Schedule::command('queue:work --stop-when-empty --max-time=50')->everyMinute()->withoutOverlapping(5);

// Heartbeat: proof the scheduler itself is firing. The /up/scheduler health route reads this and
// reports stale (503) if it hasn't updated in the last 2 minutes.
Schedule::call(fn () => Cache::put('scheduler:last-run', now()->toIso8601String(), now()->addDay()))
    ->everyMinute()
    ->name('scheduler-heartbeat');
