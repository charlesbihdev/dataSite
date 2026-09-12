<?php

/*
|--------------------------------------------------------------------------
| DOMAIN 3  ·  surface "subagent_store"  ·  e.g. databundlestore.com
|--------------------------------------------------------------------------
| The SUBAGENT's shop. BUY-ONLY, and the sealed BOTTOM of the ladder. This is
| the only surface a walk-in customer ever needs, and the only thing they can
| do is purchase.
|
| URL map:
|   /{subagentSlug}   Subagent storefront: buy bundles — NO "become" anything.
|
| LADDER RULE (the seal): no registration, no login, no recruitment CTA, and
| no link back up. Cleaning the URL down to the domain root must reveal
| NOTHING — a customer here cannot discover "become a subagent" (that lives on
| D2) or climb to any tier. Keep this domain a dead end by construction.
|
| Response is a stub; the real storefront/checkout page replaces it.
|
*/

use Illuminate\Support\Facades\Route;

Route::get('{subagentSlug}', fn (string $subagentSlug) => response("Subagent storefront for [{$subagentSlug}] — buy bundles only — TODO"))
    ->where('subagentSlug', '[A-Za-z0-9\-]+')
    ->name('subagent.storefront');
