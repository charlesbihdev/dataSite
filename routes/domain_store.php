<?php

/*
|--------------------------------------------------------------------------
| DOMAIN 3 — Subagent's shop, buy only  (e.g. databundlestore.com)
|--------------------------------------------------------------------------
|
|   /{subagentSlug}   Subagent storefront: buy bundles — NO "become" anything.
|
| No registration, no login, no recruitment CTA exists on this domain. This is
| the bottom of the ladder: a customer here cannot climb to any tier.
|
| Placeholder response is a stub; the real storefront/checkout page replaces it.
|
*/

use Illuminate\Support\Facades\Route;

Route::get('{subagentSlug}', fn (string $subagentSlug) => response("Subagent storefront for [{$subagentSlug}] — buy bundles only — TODO"))
    ->where('subagentSlug', '[A-Za-z0-9\-]+')
    ->name('store.storefront');
