<?php


use App\Http\Controllers\Api\V1\Operations\RouterController;
use App\Http\Controllers\Api\V1\Operations\DeviceSetupController;
use App\Support\Permissions;




Route::middleware(['auth:sanctum', 'active.user', 'company.context', 'company.required'])->group(function () {

    Route::get('/device-setup', [DeviceSetupController::class, 'show'])->middleware('permission:' . Permissions::DEVICE_SETUP_VIEW);

    Route::get('/routers', [RouterController::class, 'index'])->middleware('permission:' . Permissions::ROUTERS_VIEW);
    Route::post('/routers', [RouterController::class, 'store'])->middleware('permission:' . Permissions::ROUTERS_CREATE);
    Route::get('/routers/{router}', [RouterController::class, 'show'])->middleware('permission:' . Permissions::ROUTERS_VIEW);
    Route::patch('/routers/{router}', [RouterController::class, 'update'])->middleware('permission:' . Permissions::ROUTERS_UPDATE);
    Route::delete('/routers/{router}', [RouterController::class, 'destroy'])->middleware('permission:' . Permissions::ROUTERS_DELETE);
});