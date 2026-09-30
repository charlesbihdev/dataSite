<?php

use App\Models\UpstreamApiLog;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Heartbeat FIRST: proof the scheduler is firing, written before the heavier tasks below so it stays
// fresh even if a run is slow or overlaps. The /up/scheduler route reports stale (503) after 2 min.
Schedule::call(fn () => Cache::put('scheduler:last-run', now()->toIso8601String(), now()->addDay()))
    ->everyMinute()
    ->name('scheduler-heartbeat');

// Safety-net status poller: sweep every processing order and pull its DBH status (mirrors DBH's own
// cron sync). Foreground + withoutOverlapping: if one run is still busy, the next minute's copy is
// skipped by the lock rather than piling up — no background-process dependency on shared hosting.
Schedule::command('orders:poll-processing')->everyMinute()->withoutOverlapping();

// Drain the queue without a persistent worker: process pending jobs (receipt emails, the instant
// poll) then exit. Short --max-time keeps a single schedule:run comfortably under a minute.
Schedule::command('queue:work --stop-when-empty --max-time=30')->everyMinute()->withoutOverlapping(5);

// Prune upstream API logs older than 6 months (UpstreamApiLog::prunable).
Schedule::command('model:prune', ['--model' => [UpstreamApiLog::class]])->daily();
