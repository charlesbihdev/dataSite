<?php

/*
|--------------------------------------------------------------------------
| DOMAIN 1 — Admin + Agents  (e.g. datasite.com)
|--------------------------------------------------------------------------
|
|   /            Public landing
|   /register    Public — BECOME AN AGENT (open, no fee)   [Fortify, global*]
|   /login       Agent login                               [Fortify, global*]
|   /dashboard   Agent management portal
|   /admin       Superadmin backoffice (IP-locked)
|
| *Auth routes (/login, /register, /logout, 2FA) are currently served globally
|  by Fortify. Scoping them to this domain only — so agent registration cannot
|  be reached from the other domains — is a follow-up (see ARCHITECTURE.md).
|
| Placeholder responses below are stubs; real Inertia pages replace them as the
| portals are built.
|
*/

use App\Http\Controllers\Admin\AccountsController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\OrdersController;
use App\Http\Controllers\Admin\PricingController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\TransactionsController;
use App\Http\Controllers\Admin\WithdrawalsController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'dashboard')->name('dashboard');
});

// Superadmin backoffice. TODO: gate behind the admin guard + IP allowlist once multi-guard
// auth is wired (ARCHITECTURE §1). Viewable in local now so the portal can be built against
// real data; kept off in non-local environments until it's locked down.
Route::prefix('admin')->name('admin.')->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    // Sections scaffolded + routed; each fills in as its own slice.
    Route::get('orders', [OrdersController::class, 'index'])->name('orders');
    Route::post('orders/{order}/poll', [OrdersController::class, 'poll'])->name('orders.poll');
    Route::post('orders/{order}/refund', [OrdersController::class, 'refund'])->name('orders.refund');
    Route::get('pricing', [PricingController::class, 'index'])->name('pricing');
    Route::post('pricing/base-costs', [PricingController::class, 'storeBaseCost'])->name('pricing.base-costs.store');
    Route::put('pricing/base-costs/{baseCost}', [PricingController::class, 'updateBaseCost'])->name('pricing.base-costs.update');
    Route::delete('pricing/base-costs/{baseCost}', [PricingController::class, 'destroyBaseCost'])->name('pricing.base-costs.destroy');
    Route::post('pricing/tier-prices', [PricingController::class, 'storeTierPrice'])->name('pricing.tier-prices.store');
    Route::put('pricing/tier-prices/{tierPrice}', [PricingController::class, 'updateTierPrice'])->name('pricing.tier-prices.update');
    Route::delete('pricing/tier-prices/{tierPrice}', [PricingController::class, 'destroyTierPrice'])->name('pricing.tier-prices.destroy');
    Route::get('accounts', [AccountsController::class, 'index'])->name('accounts');
    Route::post('accounts/{type}', [AccountsController::class, 'store'])
        ->whereIn('type', ['agents', 'subagents'])->name('accounts.store');
    Route::post('accounts/{type}/bulk', [AccountsController::class, 'bulk'])
        ->whereIn('type', ['agents', 'subagents'])->name('accounts.bulk');
    Route::get('accounts/{type}/export', [AccountsController::class, 'exportCsv'])
        ->whereIn('type', ['agents', 'subagents'])->name('accounts.export');
    Route::post('accounts/{type}/{id}/toggle', [AccountsController::class, 'toggle'])->name('accounts.toggle');
    Route::post('accounts/{type}/{id}/funds', [AccountsController::class, 'addFunds'])->name('accounts.funds');
    Route::post('accounts/{type}/{id}/reset-password', [AccountsController::class, 'resetPassword'])
        ->name('accounts.reset-password');
    Route::delete('accounts/{type}/{id}', [AccountsController::class, 'destroy'])->name('accounts.destroy');
    Route::get('topups', [TransactionsController::class, 'topups'])->name('topups');
    Route::get('ledger', [TransactionsController::class, 'ledger'])->name('ledger');
    Route::get('withdrawals', [WithdrawalsController::class, 'index'])->name('withdrawals');
    Route::put('withdrawals/{withdrawal}', [WithdrawalsController::class, 'update'])->name('withdrawals.update');
    Route::get('settings', [SettingsController::class, 'index'])->name('settings');
    Route::put('settings/connection', [SettingsController::class, 'updateConnection'])->name('settings.connection');
});
