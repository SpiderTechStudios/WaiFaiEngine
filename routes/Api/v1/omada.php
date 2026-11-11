<?php

use App\Http\Controllers\Api\V1\Operations\OmadaIntegrationController;
use App\Support\Permissions;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'active.user', 'company.context', 'company.required', 'subscription.active'])->group(function () {
    Route::prefix('omada/integrations')->group(function () {
        Route::get('/', [OmadaIntegrationController::class, 'index'])->middleware('permission:'.Permissions::ROUTERS_VIEW);
        Route::post('/', [OmadaIntegrationController::class, 'store'])->middleware('permission:'.Permissions::ROUTERS_CREATE);
        Route::post('/test', [OmadaIntegrationController::class, 'testConnection'])->middleware('permission:'.Permissions::ROUTERS_VIEW);

        Route::get('/{integration}', [OmadaIntegrationController::class, 'show'])->middleware('permission:'.Permissions::ROUTERS_VIEW);
        Route::patch('/{integration}', [OmadaIntegrationController::class, 'update'])->middleware('permission:'.Permissions::ROUTERS_UPDATE);
        Route::delete('/{integration}', [OmadaIntegrationController::class, 'destroy'])->middleware('permission:'.Permissions::ROUTERS_DELETE);

        Route::get('/{integration}/status', [OmadaIntegrationController::class, 'status'])->middleware('permission:'.Permissions::ROUTERS_VIEW);
        Route::post('/{integration}/test', [OmadaIntegrationController::class, 'test'])->middleware('permission:'.Permissions::ROUTERS_VIEW);
        Route::post('/{integration}/sync', [OmadaIntegrationController::class, 'sync'])->middleware('permission:'.Permissions::ROUTERS_SYNC);

        Route::get('/{integration}/sites', [OmadaIntegrationController::class, 'sites'])->middleware('permission:'.Permissions::ROUTERS_VIEW);
        Route::put('/{integration}/site', [OmadaIntegrationController::class, 'selectSite'])->middleware('permission:'.Permissions::ROUTERS_UPDATE);

        Route::get('/{integration}/access-points', [OmadaIntegrationController::class, 'accessPoints'])->middleware('permission:'.Permissions::ROUTERS_VIEW);
        Route::put('/{integration}/access-points', [OmadaIntegrationController::class, 'selectAccessPoints'])->middleware('permission:'.Permissions::ROUTERS_UPDATE);

        Route::get('/{integration}/ssids', [OmadaIntegrationController::class, 'ssids'])->middleware('permission:'.Permissions::ROUTERS_VIEW);
        Route::put('/{integration}/ssid', [OmadaIntegrationController::class, 'selectSsid'])->middleware('permission:'.Permissions::ROUTERS_UPDATE);

        Route::get('/{integration}/sessions', [OmadaIntegrationController::class, 'sessions'])->middleware('permission:'.Permissions::ROUTERS_VIEW);
        Route::post('/{integration}/clients/{mac}/disconnect', [OmadaIntegrationController::class, 'disconnectClient'])->middleware('permission:'.Permissions::ROUTERS_UPDATE);
    });
});
