<?php

use App\Http\Controllers\Api\V1\Operations\InstallationRequestController;
use App\Support\Permissions;
use Illuminate\Support\Facades\Route;

Route::middleware([
    'auth:sanctum',
    'active.user',
    'company.context',
    'company.required',
    'subscription.active',
])->group(function () {
    Route::get('/installation-requests', [InstallationRequestController::class, 'index'])
        ->middleware('permission:'.Permissions::INSTALLATION_REQUESTS_VIEW);

    Route::post('/installation-requests', [InstallationRequestController::class, 'store'])
        ->middleware('permission:'.Permissions::INSTALLATION_REQUESTS_CREATE);

    Route::get('/installation-requests/{installationRequest}', [InstallationRequestController::class, 'show'])
        ->middleware('permission:'.Permissions::INSTALLATION_REQUESTS_VIEW);

    Route::post('/installation-requests/{installationRequest}/payments', [InstallationRequestController::class, 'startPayment'])
        ->middleware(['permission:'.Permissions::INSTALLATION_REQUESTS_CREATE, 'throttle:20,1']);

    Route::get('/installation-requests/{installationRequest}/payments/{payment}', [InstallationRequestController::class, 'showPayment'])
        ->middleware('permission:'.Permissions::INSTALLATION_REQUESTS_VIEW);
});
