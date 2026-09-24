<?php

use App\Http\Controllers\Api\V1\Portal\PortalController;
use Illuminate\Support\Facades\Route;

Route::prefix('portal/{subdomain}')
    ->middleware(['portal.company'])
    ->group(function () {
        Route::get('/', [PortalController::class, 'bootstrap'])
            ->middleware('throttle:60,1');

        Route::post('/payments', [PortalController::class, 'createPayment'])
            ->middleware('throttle:portal-payment-create');

        Route::get('/payments/{payment}', [PortalController::class, 'showPayment'])
            ->middleware('throttle:portal-payment-status');

        Route::post('/vouchers/redeem', [PortalController::class, 'redeemVoucher'])
            ->middleware('throttle:10,1');

        Route::post('/offers/claim', [PortalController::class, 'claimOffer'])
            ->middleware('throttle:10,1');

        Route::post('/sessions', [PortalController::class, 'createSession'])
            ->middleware('throttle:20,1');

        Route::post('/restore', [PortalController::class, 'restore'])
            ->middleware('throttle:10,1');
    });
