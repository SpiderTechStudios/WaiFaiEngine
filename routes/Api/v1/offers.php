<?php

use App\Http\Controllers\Api\V1\Operations\OfferController;
use App\Support\Permissions;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'active.user', 'company.context', 'company.required', 'subscription.active'])->group(function () {
    Route::get('/offers', [OfferController::class, 'index'])->middleware('permission:'.Permissions::OFFERS_VIEW);
    Route::post('/offers', [OfferController::class, 'store'])->middleware('permission:'.Permissions::OFFERS_CREATE);
    Route::get('/offers/{offer}', [OfferController::class, 'show'])->middleware('permission:'.Permissions::OFFERS_VIEW);
    Route::patch('/offers/{offer}', [OfferController::class, 'update'])->middleware('permission:'.Permissions::OFFERS_UPDATE);
    Route::delete('/offers/{offer}', [OfferController::class, 'destroy'])->middleware('permission:'.Permissions::OFFERS_DELETE);
    Route::post('/offers/{offer}/activate', [OfferController::class, 'activate'])->middleware('permission:'.Permissions::OFFERS_UPDATE);
    Route::post('/offers/{offer}/deactivate', [OfferController::class, 'deactivate'])->middleware('permission:'.Permissions::OFFERS_UPDATE);
});
