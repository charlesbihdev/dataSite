<?php

use App\Http\Controllers\Api\OrderController;
use Illuminate\Support\Facades\Route;

/*
 * Developer API — authenticated by X-API-Key (see AuthenticateApiKey). Endpoint names shadow
 * Databundleshub's so an agent's existing DBH integration ports across with minimal change.
 */
Route::middleware(['api.key', 'throttle:60,1'])->group(function (): void {
    Route::post('/create_order', [OrderController::class, 'store']);
    Route::post('/developer/purchase', [OrderController::class, 'store']);

    Route::get('/order-status/{reference}', [OrderController::class, 'show']);
    Route::get('/developer/purchase-status/{reference}', [OrderController::class, 'show']);
});
