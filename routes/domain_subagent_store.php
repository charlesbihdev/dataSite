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
| The store is keyed on the subagent's username-derived handle (slug), never the
| id, so the public link is always /{username}.
|
*/

use App\Http\Controllers\Storefront\SubagentStorefrontController;
use Illuminate\Support\Facades\Route;

// Buy-only subagent storefront. Fixed sub-segments (checkout/callback/receipt/track) sit under the
// same {subagentSlug} prefix; the slug constraint keeps the root store off reserved paths.
Route::prefix('{subagentSlug}')->where(['subagentSlug' => '[A-Za-z0-9\-]+', 'order' => '[A-Za-z0-9\-]+'])->group(function () {
    Route::get('/', [SubagentStorefrontController::class, 'show'])->name('subagent.storefront');
    Route::post('checkout', [SubagentStorefrontController::class, 'checkout'])->name('subagent.storefront.checkout');
    Route::get('callback', [SubagentStorefrontController::class, 'paymentCallback'])->name('subagent.storefront.callback');
    Route::get('receipt/{order}', [SubagentStorefrontController::class, 'receipt'])->name('subagent.storefront.receipt');
    Route::get('track', [SubagentStorefrontController::class, 'track'])->name('subagent.storefront.track');
});
