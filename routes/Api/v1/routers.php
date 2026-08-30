<?php

use App\Http\Controllers\Api\V1\Operations\RouterController;
use App\Support\Permissions;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'active.user', 'company.context', 'company.required', 'subscription.active'])->group(function () {
    Route::get('/routers', [RouterController::class, 'index'])->middleware('permission:'.Permissions::ROUTERS_VIEW);
    Route::post('/routers', [RouterController::class, 'store'])->middleware('permission:'.Permissions::ROUTERS_CREATE);
    Route::get('/routers/{router}', [RouterController::class, 'show'])->middleware('permission:'.Permissions::ROUTERS_VIEW);
    Route::patch('/routers/{router}', [RouterController::class, 'update'])->middleware('permission:'.Permissions::ROUTERS_UPDATE);
    Route::delete('/routers/{router}', [RouterController::class, 'destroy'])->middleware('permission:'.Permissions::ROUTERS_DELETE);
    Route::post('/routers/{router}/sync', [RouterController::class, 'sync'])->middleware('permission:'.Permissions::ROUTERS_SYNC);
});
