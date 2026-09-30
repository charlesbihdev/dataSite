<?php

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;

// Scheduler health: reports whether `schedule:run` is actually firing in prod. The heartbeat
// (routes/console.php) refreshes this every minute; older than 2 minutes → stale (HTTP 503).
Route::get('up/scheduler', function () {
    $last = Cache::get('scheduler:last-run');
    $secondsAgo = $last !== null ? (int) Carbon::parse($last)->diffInSeconds(now()) : null;
    $ok = $secondsAgo !== null && $secondsAgo < 120;

    return response()->json([
        'ok' => $ok,
        'last_run' => $last,
        'seconds_ago' => $secondsAgo,
    ], $ok ? 200 : 503);
});

/*
|--------------------------------------------------------------------------
| Domain dispatcher
|--------------------------------------------------------------------------
| The ONLY place domains are referenced. Each of the three domains is bound to
| its own route file. See config/surfaces.php and ARCHITECTURE.md.
|
| Prod: SURFACE_*_DOMAIN env vars set -> true per-domain separation.
| Local: env vars unset -> fall back to path prefixes (/, /agent-store,
|        /subagent-store) so the whole app is reachable on one host in dev.
*/

$surface = function (string $key, string $file): void {
    $domain = config("surfaces.{$key}");

    $group = $domain
        ? Route::domain($domain)
        : Route::prefix((string) config("surfaces.local_prefixes.{$key}", ''));

    $group->middleware("surface:{$key}")->group(base_path("routes/{$file}"));
};

$surface('admin_agents', 'domain_admin_agents.php');
$surface('agent_store', 'domain_agent_store.php');
$surface('subagent_store', 'domain_subagent_store.php');

// Agent account settings (profile/security/password/appearance) belong to the
// platform domain too — bind them through the same dispatcher so they are NOT
// reachable from the store domains in prod.
$surface('admin_agents', 'settings.php');
