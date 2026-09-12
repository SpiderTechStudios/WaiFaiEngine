<?php

use App\Http\Controllers\Api\V1\Operations\PaymentController;
use App\Http\Controllers\Api\V1\Operations\TestPaymentController;
use App\Support\Permissions;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'active.user', 'company.context', 'company.required', 'subscription.active'])->group(function () {
    Route::get('/payments', [PaymentController::class, 'index'])->middleware('permission:'.Permissions::PAYMENTS_VIEW);
    Route::post('/payments', [PaymentController::class, 'store'])->middleware('permission:'.Permissions::PAYMENTS_CREATE);
    Route::get('/payments/{payment}', [PaymentController::class, 'show'])->middleware('permission:'.Permissions::PAYMENTS_VIEW);
});

/*
| Superadmin dry-run against a live payment provider.
| Does not create platform_payments / payment_transactions rows.
*/
Route::prefix('test/payments')->middleware(['auth:sanctum', 'active.user', 'superadmin'])->group(function () {
    Route::post('/{provider}', [TestPaymentController::class, 'testCollection'])
        ->where('provider', '[A-Za-z0-9_-]+');
});


Route::get('/test/env', function () {
    return response()->json([
        'env' => env('APP_ENV'),
        'debug' => env('APP_DEBUG'),
        'palmpesa_token' => env('PALMPESA_API_TOKEN'),
        'palmpesa_userid' => env('PALMPESA_USER_ID'),
    ]);
});
