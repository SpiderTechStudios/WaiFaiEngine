<?php

use App\Http\Controllers\Api\V1\Operations\CartController;
use App\Http\Controllers\Api\V1\Operations\MarketplaceController;
use App\Http\Controllers\Api\V1\Operations\OrderController;
use App\Http\Controllers\Api\V1\SuperAdmin\AdminOrderController;
use Illuminate\Support\Facades\Route;

/*
| Device marketplace, cart, and purchase.
|
| Payment purposes stay separate from the other platform collections:
| - platform_subscription / subscription_renewal
| - installation_request
| - device_purchase
|
| Order status: pending → processing → in-transit → delivered | cancelled
*/

Route::middleware([
    'auth:sanctum',
    'active.user',
    'company.context',
    'company.required',
])->group(function () {
    Route::get('/marketplace/devices', [MarketplaceController::class, 'index']);
    Route::get('/marketplace/devices/{device}', [MarketplaceController::class, 'show']);

    Route::get('/cart', [CartController::class, 'show']);
    Route::post('/cart/items', [CartController::class, 'add']);
    Route::patch('/cart/items/{cartItem}', [CartController::class, 'update']);
    Route::delete('/cart/items/{cartItem}', [CartController::class, 'remove']);
    Route::delete('/cart', [CartController::class, 'clear']);
    Route::post('/cart/checkout', [CartController::class, 'checkout']);

    Route::get('/orders', [OrderController::class, 'index']);
    Route::get('/orders/{order}', [OrderController::class, 'show']);
    Route::post('/orders/{order}/confirm-delivery', [OrderController::class, 'confirmDelivery']);
});

Route::prefix('superadmin')
    ->middleware(['auth:sanctum', 'active.user', 'superadmin'])
    ->group(function () {
        Route::get('/orders', [AdminOrderController::class, 'index']);
        Route::get('/orders/{order}', [AdminOrderController::class, 'show']);
        Route::post('/orders/{order}/in-transit', [AdminOrderController::class, 'inTransit']);
        Route::post('/orders/{order}/delivered', [AdminOrderController::class, 'delivered']);
        Route::post('/orders/{order}/cancel', [AdminOrderController::class, 'cancel']);
    });
