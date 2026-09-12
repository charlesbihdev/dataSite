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

use App\Http\Controllers\Admin\AccountApiKeysController;
use App\Http\Controllers\Admin\AccountsController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\OrdersController;
use App\Http\Controllers\Admin\PaymentConfigController;
use App\Http\Controllers\Admin\PricingController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\TransactionsController;
use App\Http\Controllers\Admin\WithdrawalsController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'dashboard')->name('dashboard');
});

use App\Http\Controllers\Admin\AnalyticsController;

// Superadmin backoffice. Protected by admin.ip allowlist and accessible in local/testing.
Route::prefix('admin')->name('admin.')->middleware(['admin.ip'])->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('analytics', [AnalyticsController::class, 'index'])->name('analytics');

    // Sections scaffolded + routed; each fills in as its own slice.
    Route::redirect('orders', 'admin/orders/agent')->name('orders');
    Route::get('orders/agent', [OrdersController::class, 'agent'])->name('orders.agent');
    Route::get('orders/regular', [OrdersController::class, 'regular'])->name('orders.regular');
    Route::get('orders/export', [OrdersController::class, 'export'])->name('orders.export');
    Route::post('orders/bulk', [OrdersController::class, 'bulk'])->name('orders.bulk');
    Route::post('orders/{order}/poll', [OrdersController::class, 'poll'])->name('orders.poll');
    Route::post('orders/{order}/refund', [OrdersController::class, 'refund'])->name('orders.refund');
    Route::post('orders/{order}/verify-payment', [OrdersController::class, 'verifyPayment'])->name('orders.verify-payment');
    Route::post('orders/{order}/mark-verified', [OrdersController::class, 'markVerified'])->name('orders.mark-verified');
    Route::delete('orders/{order}', [OrdersController::class, 'destroy'])->name('orders.destroy');
    Route::get('pricing', [PricingController::class, 'index'])->name('pricing');
    Route::post('pricing/base-costs', [PricingController::class, 'storeBaseCost'])->name('pricing.base-costs.store');
    Route::put('pricing/base-costs/{baseCost}', [PricingController::class, 'updateBaseCost'])->name('pricing.base-costs.update');
    Route::delete('pricing/base-costs/{baseCost}', [PricingController::class, 'destroyBaseCost'])->name('pricing.base-costs.destroy');
    Route::post('pricing/tier-prices', [PricingController::class, 'storeTierPrice'])->name('pricing.tier-prices.store');
    Route::put('pricing/tier-prices/{tierPrice}', [PricingController::class, 'updateTierPrice'])->name('pricing.tier-prices.update');
    Route::delete('pricing/tier-prices/{tierPrice}', [PricingController::class, 'destroyTierPrice'])->name('pricing.tier-prices.destroy');
    Route::post('pricing/tiers', [PricingController::class, 'storeTier'])->name('pricing.tiers.store');
    Route::put('pricing/tiers/{pricingTier}', [PricingController::class, 'updateTier'])->name('pricing.tiers.update');
    Route::delete('pricing/tiers/{pricingTier}', [PricingController::class, 'destroyTier'])->name('pricing.tiers.destroy');
    Route::post('pricing/clone-network', [PricingController::class, 'cloneNetwork'])->name('pricing.clone-network');
    Route::post('pricing/reset-network', [PricingController::class, 'resetNetwork'])->name('pricing.reset-network');
    Route::get('accounts', [AccountsController::class, 'index'])->name('accounts');
    Route::post('accounts/{type}', [AccountsController::class, 'store'])
        ->whereIn('type', ['agents', 'subagents'])->name('accounts.store');
    Route::post('accounts/{type}/bulk', [AccountsController::class, 'bulk'])
        ->whereIn('type', ['agents', 'subagents'])->name('accounts.bulk');
    Route::get('accounts/{type}/export', [AccountsController::class, 'exportCsv'])
        ->whereIn('type', ['agents', 'subagents'])->name('accounts.export');
    Route::post('accounts/{type}/{id}/toggle', [AccountsController::class, 'toggle'])->name('accounts.toggle');
    Route::post('accounts/{type}/{id}/funds', [AccountsController::class, 'addFunds'])->name('accounts.funds');
    Route::put('accounts/agents/{id}/tier', [AccountsController::class, 'assignTier'])->name('accounts.assign-tier');
    Route::post('accounts/{type}/{id}/reset-password', [AccountsController::class, 'resetPassword'])
        ->name('accounts.reset-password');
    Route::delete('accounts/{type}/{id}', [AccountsController::class, 'destroy'])->name('accounts.destroy');
    Route::post('accounts/{type}/{id}/api-keys', [AccountApiKeysController::class, 'store'])
        ->whereIn('type', ['agents', 'subagents'])->name('accounts.api-keys.store');
    Route::post('api-keys/{apiKey}/toggle', [AccountApiKeysController::class, 'toggle'])->name('api-keys.toggle');
    Route::delete('api-keys/{apiKey}', [AccountApiKeysController::class, 'destroy'])->name('api-keys.destroy');

    Route::get('ledger', [TransactionsController::class, 'ledger'])->name('ledger');
    Route::get('withdrawals', [WithdrawalsController::class, 'index'])->name('withdrawals');
    Route::put('withdrawals/{withdrawal}', [WithdrawalsController::class, 'update'])->name('withdrawals.update');
    Route::get('payment-config', [PaymentConfigController::class, 'index'])->name('payment-config');
    Route::put('payment-config/paystack', [PaymentConfigController::class, 'updatePaystack'])->name('payment-config.paystack');
    Route::put('payment-config/moolre', [PaymentConfigController::class, 'updateMoolre'])->name('payment-config.moolre');
    Route::get('settings', [SettingsController::class, 'index'])->name('settings');
    Route::put('settings/connection', [SettingsController::class, 'updateConnection'])->name('settings.connection');
    Route::put('settings/email', [SettingsController::class, 'updateEmail'])->name('settings.email');
    Route::post('settings/email/test', [SettingsController::class, 'sendTestEmail'])->name('settings.email.test');
    Route::put('settings/registration', [SettingsController::class, 'updateRegistration'])->name('settings.registration');
});
