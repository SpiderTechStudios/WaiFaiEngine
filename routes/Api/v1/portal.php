<?php

use App\Http\Controllers\Api\V1\Portal\PortalController;
use Illuminate\Support\Facades\Route;

Route::prefix('portal/{subdomain}')
    ->middleware(['portal.company'])
    ->group(function () {
        Route::get('/', [PortalController::class, 'bootstrap'])
            ->middleware('throttle:120,1');

        Route::post('/payments', [PortalController::class, 'createPayment'])
            ->middleware('throttle:portal-payment-create');

        Route::get('/payments/{payment}', [PortalController::class, 'showPayment'])
            ->middleware('throttle:portal-payment-status');

        Route::post('/vouchers/redeem', [PortalController::class, 'redeemVoucher'])
            ->middleware('throttle:portal-voucher');

        Route::post('/offers/claim', [PortalController::class, 'claimOffer'])
            ->middleware('throttle:portal-offer');

        Route::post('/sessions', [PortalController::class, 'createSession'])
            ->middleware('throttle:portal-session');

        Route::post('/restore', [PortalController::class, 'restore'])
            ->middleware('throttle:portal-restore');
    });
