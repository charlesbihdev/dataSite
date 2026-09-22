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

use App\Http\Controllers\Storefront\StorefrontController;
use App\Http\Controllers\Subagent\AuthController;
use App\Http\Controllers\Subagent\DashboardController;
use App\Http\Controllers\Subagent\OrdersController;
use App\Http\Controllers\Subagent\PackagesController;
use App\Http\Controllers\Subagent\RegisterController;
use App\Http\Controllers\Subagent\SettingsController;
use App\Http\Controllers\Subagent\StoreLinkController;
use App\Http\Controllers\Subagent\TransactionsController;
use App\Http\Controllers\Subagent\WithdrawalController;
use Illuminate\Support\Facades\Route;

// D2 domain root — no public catalog here (every shop is a reseller's /buy/{slug} link). Politely
// point stray visitors to use their store link; exposes NO signup, keeping the firewall intact.
Route::inertia('/', 'public/agent-store-landing')->name('agent_store.home');

// Subagent auth lives ONLY on this domain and uses its own guard + controller —
// never Fortify's (agent) login. Guests land here; the guest redirect for the
// agent_store surface points at subagent.login (see bootstrap/app.php).
Route::middleware(['guest:subagent'])->group(function () {
    Route::get('login', [AuthController::class, 'showLoginForm'])->name('subagent.login');
    Route::post('login', [AuthController::class, 'login'])->name('subagent.login.store');

    // Become a subagent under an agent (ladder middle rung — D2 only, keyed to ?ref={agentSlug}).
    Route::get('register', [RegisterController::class, 'create'])->name('subagent.register');
    Route::post('register', [RegisterController::class, 'store'])->name('subagent.register.store');
});

Route::middleware(['auth:subagent'])->group(function () {
    Route::post('logout', [AuthController::class, 'logout'])->name('subagent.logout');

    // Subagent dashboard. Path is /dashboard on THIS domain (distinct route
    // from the agent dashboard on domain 1, which shares the /dashboard path).
    Route::get('dashboard', [DashboardController::class, 'index'])->name('subagent.dashboard');

    // Withdrawal — payout of matured earnings (mirrors the agent flow, subagent-scoped).
    Route::get('withdrawals', [WithdrawalController::class, 'index'])->name('subagent.withdrawals');
    Route::post('withdrawals', [WithdrawalController::class, 'store'])->name('subagent.withdrawals.store');
    Route::post('withdrawals/{withdrawal}/cancel', [WithdrawalController::class, 'cancel'])->name('subagent.withdrawals.cancel');

    // Orders — the subagent's own storefront sales (read-only list).
    Route::get('orders', [OrdersController::class, 'index'])->name('subagent.orders');

    // Transactions — the subagent's earnings/commission ledger (read-only; no deposit wallet).
    Route::get('transactions', [TransactionsController::class, 'index'])->name('subagent.transactions');

    // Packages — the subagent's own selling prices, cost frozen from the agent's sub-agent price.
    Route::get('packages', [PackagesController::class, 'index'])->name('subagent.packages');
    Route::post('packages', [PackagesController::class, 'store'])->name('subagent.packages.store');
    Route::post('packages/{package}/toggle', [PackagesController::class, 'toggle'])->name('subagent.packages.toggle');
    Route::delete('packages/{package}', [PackagesController::class, 'destroy'])->name('subagent.packages.destroy');

    // Store Link — the subagent's storefront link, QR, performance, contact + store on/off.
    Route::get('store-link', [StoreLinkController::class, 'index'])->name('subagent.store-link');
    Route::put('store-link/contact', [StoreLinkController::class, 'updateContact'])->name('subagent.store-link.contact');
    Route::post('store-link/qr', [StoreLinkController::class, 'generateQr'])->name('subagent.store-link.qr');
    Route::post('store-link/toggle', [StoreLinkController::class, 'toggleStore'])->name('subagent.store-link.toggle');

    // Settings — profile + password (self-contained, subagent-scoped).
    Route::get('settings', [SettingsController::class, 'edit'])->name('subagent.settings');
    Route::patch('settings/profile', [SettingsController::class, 'updateProfile'])->name('subagent.settings.profile');
    Route::put('settings/password', [SettingsController::class, 'updatePassword'])->name('subagent.settings.password');
});

// Agent public storefront — buy bundles from an agent's retail store. The customer link is
// /buy/{slug} (what the referral QR encodes). Public: no auth. Registered last so it never shadows
// the fixed routes above; the slug constraint keeps reserved paths out.
Route::prefix('buy/{agentSlug}')->where(['agentSlug' => '[A-Za-z0-9\-]+', 'order' => '[A-Za-z0-9\-]+'])->group(function () {
    Route::get('/', [StorefrontController::class, 'show'])->name('agent.storefront');
    Route::post('checkout', [StorefrontController::class, 'checkout'])->name('agent.storefront.checkout');
    Route::get('callback', [StorefrontController::class, 'paymentCallback'])->name('agent.storefront.callback');
    Route::get('receipt/{order}', [StorefrontController::class, 'receipt'])->name('agent.storefront.receipt');
    Route::get('track', [StorefrontController::class, 'track'])->name('agent.storefront.track');
});
