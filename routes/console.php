<?php

use App\Models\UpstreamApiLog;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Prune upstream API logs older than 6 months (UpstreamApiLog::prunable). Runs off the existing
// every-minute schedule:run cron — no extra prod cron needed.
Schedule::command('model:prune', ['--model' => [UpstreamApiLog::class]])->daily();
