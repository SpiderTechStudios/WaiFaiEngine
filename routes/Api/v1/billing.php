<?php

use App\Http\Controllers\Api\V1\Billing\BillingController;
use Illuminate\Support\Facades\Route;

// Billing stays reachable when subscription is expired so tenants can renew.
Route::middleware(['auth:sanctum', 'active.user', 'company.context', 'company.required'])->group(function () {
    Route::get('/billing/subscription', [BillingController::class, 'subscription']);
    Route::post('/billing/subscription/payments', [BillingController::class, 'startRenewal'])
        ->middleware('throttle:20,1');
    Route::get('/billing/subscription/payments/{payment}', [BillingController::class, 'showPayment']);
});
