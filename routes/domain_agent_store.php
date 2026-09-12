<?php

/*
|--------------------------------------------------------------------------
| DOMAIN 2  ·  surface "agent_store"  ·  e.g. dataagentshub.com
|--------------------------------------------------------------------------
| The AGENT's shop, and the home of the SUBAGENT tier. Customers buy from an
| agent's storefront here, and this is where subagents sign up + run their
| portal. One rung DOWN from Domain 1.
|
| URL map:
|   /login          Subagent login                        [auth:subagent]
|   /dashboard      Subagent portal
|   /{agentSlug}    Agent storefront: buy bundles + BECOME A SUBAGENT
|
| LADDER RULE: "Become a subagent" is the MIDDLE rung and exists ONLY on this
| domain — a customer of the buy-only store domain (D3) cannot reach it. A
| customer here MAY still climb higher (become an agent) by visiting D1; that
| upward path is allowed. Only downward discovery is sealed.
|
| ROUTING NOTE: the catch-all {agentSlug} storefront is registered LAST so it
| never shadows the fixed routes above; the slug constraint keeps it from
| capturing reserved paths. Real reserved-word handling lands with the build.
|
| Responses are stubs; real Inertia pages replace them later.
|
*/

use App\Http\Controllers\Subagent\AuthController;
use Illuminate\Support\Facades\Route;

// Subagent auth lives ONLY on this domain and uses its own guard + controller —
// never Fortify's (agent) login. Guests land here; the guest redirect for the
// agent_store surface points at subagent.login (see bootstrap/app.php).
Route::middleware(['guest:subagent'])->group(function () {
    Route::get('login', [AuthController::class, 'showLoginForm'])->name('subagent.login');
    Route::post('login', [AuthController::class, 'login'])->name('subagent.login.store');
});

Route::middleware(['auth:subagent'])->group(function () {
    Route::post('logout', [AuthController::class, 'logout'])->name('subagent.logout');

    // Subagent dashboard. Path is /dashboard on THIS domain (distinct route
    // from the agent dashboard on domain 1, which shares the /dashboard path).
    Route::get('dashboard', fn () => response('Subagent portal — TODO'))
        ->name('subagent.dashboard');
});

// Agent public storefront — buy bundles + recruit subagents. Registered last.
Route::get('{agentSlug}', fn (string $agentSlug) => response("Agent storefront for [{$agentSlug}] — buy bundles + become a subagent — TODO"))
    ->where('agentSlug', '[A-Za-z0-9\-]+')
    ->name('agent.storefront');
