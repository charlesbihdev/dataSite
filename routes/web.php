<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Domain dispatcher
|--------------------------------------------------------------------------
| The ONLY place domains are referenced. Each of the three domains is bound to
| its own route file. See config/surfaces.php and ARCHITECTURE.md.
|
| Prod: SURFACE_*_DOMAIN env vars set -> true per-domain separation.
| Local: env vars unset -> fall back to path prefixes (/, /agents, /store) so
|        the whole app is reachable on one host during development.
*/

$surface = function (string $key, string $file, string $localPrefix): void {
    $domain = config("surfaces.{$key}");

    $group = $domain
        ? Route::domain($domain)
        : Route::prefix($localPrefix);

    $group->middleware("surface:{$key}")->group(base_path("routes/{$file}"));
};

$surface('admin', 'domain_admin.php', '');
$surface('agents', 'domain_agents.php', 'agents');
$surface('store', 'domain_store.php', 'store');

require __DIR__.'/settings.php';
