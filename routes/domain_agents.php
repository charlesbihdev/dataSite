<?php

/*
|--------------------------------------------------------------------------
| DOMAIN 2 — Agent's shop + where subagents live  (e.g. dataagentshub.com)
|--------------------------------------------------------------------------
|
|   /login          Subagent login                    [auth — TODO scope]
|   /dashboard      Subagent management portal
|   /{agentSlug}    Agent storefront: buy bundles + BECOME A SUBAGENT
|
| The "become a subagent" flow lives ONLY here, so it cannot be reached from
| the store domain. The {agentSlug} storefront is registered LAST so it never
| shadows the fixed routes above; a slug constraint keeps it from capturing
| reserved paths. Real reserved-word handling comes with the storefront build.
|
| Placeholder responses are stubs; real Inertia pages replace them later.
|
*/

use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    // Subagent dashboard. Path is /dashboard on THIS domain (distinct route
    // from the agent dashboard on domain 1, which shares the /dashboard path).
    Route::get('dashboard', fn () => response('Subagent portal — TODO'))
        ->name('agents.dashboard');
});

// Agent public storefront — buy bundles + recruit subagents. Registered last.
Route::get('{agentSlug}', fn (string $agentSlug) => response("Agent storefront for [{$agentSlug}] — buy bundles + become a subagent — TODO"))
    ->where('agentSlug', '[A-Za-z0-9\-]+')
    ->name('agents.storefront');
